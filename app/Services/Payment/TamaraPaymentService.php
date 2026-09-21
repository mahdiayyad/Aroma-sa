<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tamara — "Buy Now, Pay Later" (pay in instalments / pay later). Uses the
 * Tamara Checkout API: create a checkout session, redirect the buyer to the
 * returned checkout_url, then verify the order on return.
 *
 * Also covers the rest of Tamara's merchant checklist: pre-checkout
 * eligibility, webhook authentication + registration, and the post-payment
 * operations (capture / cancel / refund).
 *
 * Docs: https://docs.tamara.co/
 */
class TamaraPaymentService implements PaymentGateway
{
    /** Order statuses Tamara reports as "the customer has paid / committed". */
    private const PAID_STATUSES = ['approved', 'authorised', 'fully_captured', 'partially_captured'];

    /** Order statuses meaning the money has actually been captured. */
    private const CAPTURED_STATUSES = ['fully_captured', 'partially_captured'];

    private string $apiToken;
    private string $notificationToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiToken          = (string) config('services.tamara.api_token', '');
        $this->notificationToken = (string) config('services.tamara.notification_token', '');
        $this->baseUrl           = rtrim((string) config('services.tamara.base_url', 'https://api.tamara.co'), '/');
    }

    public function key(): string
    {
        return 'tamara';
    }

    /* Checkout ------------------------------------------------------------ */

    public function createCheckout(Order $order): array
    {
        if (! $this->apiToken) {
            return ['success' => false, 'error' => 'Tamara is not configured.'];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->post("{$this->baseUrl}/checkout", $this->checkoutPayload($order));

            if (! $response->successful()) {
                Log::error('Tamara checkout failed', [
                    'order'  => $order->order_number,
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                return ['success' => false, 'error' => __('checkout.errors.bnpl_unavailable', ['gateway' => 'Tamara'])];
            }

            $data    = $response->json();
            $url     = $data['checkout_url'] ?? null;
            $orderId = $data['order_id'] ?? null;

            if (! $url) {
                return ['success' => false, 'error' => __('checkout.errors.bnpl_unavailable', ['gateway' => 'Tamara'])];
            }

            return ['success' => true, 'redirect_url' => $url, 'reference' => (string) $orderId];
        } catch (Exception $e) {
            Log::error('Tamara error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pre-checkout eligibility — whether Tamara should be offered to this
     * customer for this checkout. Fail-open by design (Tamara's own rule): no
     * phone, a timeout, or any error all mean "show it normally", so a slow
     * or unavailable eligibility service can never block a sale. Definitive
     * answers are cached briefly so a page refresh doesn't re-query Tamara.
     *
     * Docs: POST /pre-checkout/v1/eligibility -> { is_eligible: bool }
     */
    public function checkEligibility(float $amount, ?string $phone, ?string $email = null): bool
    {
        if (! $this->apiToken) {
            return true;
        }

        $phone = $this->normalizePhone($phone);

        if ($phone === null) {
            return true;
        }

        $amount   = round($amount, 2);
        $cacheKey = 'tamara.eligibility.'.md5($phone.'|'.number_format($amount, 2, '.', '').'|'.(string) $email);
        $cached   = Cache::get($cacheKey);

        if ($cached !== null) {
            return (bool) $cached;
        }

        $timeout = max(0.1, ((int) config('services.tamara.eligibility_timeout_ms', 800)) / 1000);

        try {
            $customer = ['phone_number' => $phone];
            if ($email) {
                $customer['email'] = $email;
            }

            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->withOptions(['timeout' => $timeout, 'connect_timeout' => $timeout])
                ->post("{$this->baseUrl}/pre-checkout/v1/eligibility", [
                    'order'    => ['amount' => $amount, 'currency' => 'SAR'],
                    'customer' => $customer,
                ]);

            $eligible = $response->successful() ? $response->json('is_eligible') : null;

            if (! is_bool($eligible)) {
                Log::warning('Tamara eligibility check inconclusive', ['status' => $response->status()]);

                return true;
            }

            Cache::put($cacheKey, $eligible, now()->addMinutes(5));

            return $eligible;
        } catch (Exception $e) {
            Log::warning('Tamara eligibility check failed open', ['error' => $e->getMessage()]);

            return true;
        }
    }

    public function fetchStatus(string $reference): ?array
    {
        $data = $this->getOrder($reference);

        if (! $data) {
            return null;
        }

        $status = strtolower((string) ($data['status'] ?? ''));

        // Tamara treats "approved" as a risk pre-approval only — the order is
        // not actually confirmed unless the merchant explicitly authorises it
        // (or auto-authorisation is enabled on the account, in which case the
        // order moves on to fully_captured by itself). Authorising is
        // idempotent on Tamara's side, so re-doing it on every check is safe.
        if ($status === 'approved') {
            $this->authoriseOrder((string) ($data['order_id'] ?? $reference));
            $refetched = $this->getOrder($reference);
            if ($refetched) {
                $data   = $refetched;
                $status = strtolower((string) ($data['status'] ?? $status));
            }
        }

        return [
            'paid'     => in_array($status, self::PAID_STATUSES, true),
            'captured' => in_array($status, self::CAPTURED_STATUSES, true),
            'status'   => $status,
            'raw'      => $data,
        ];
    }

    /** @return array<string,mixed>|null */
    private function getOrder(string $reference): ?array
    {
        if (! $this->apiToken || $reference === '') {
            return null;
        }

        try {
            $response = Http::withToken($this->apiToken)->acceptJson()
                ->get("{$this->baseUrl}/orders/{$reference}");

            return $response->successful() ? $response->json() : null;
        } catch (Exception $e) {
            Log::error('Tamara status error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Locks in a Tamara order that is sitting in "approved" (pre-authorised).
     * Without this call (and without auto-authorisation) Tamara voids the
     * order after its approval window and the merchant never gets paid.
     *
     * Docs: POST /orders/{order_id}/authorise
     */
    public function authoriseOrder(string $tamaraOrderId): bool
    {
        if (! $this->apiToken || $tamaraOrderId === '') {
            return false;
        }

        try {
            $response = Http::withToken($this->apiToken)->acceptJson()
                ->post("{$this->baseUrl}/orders/{$tamaraOrderId}/authorise");

            if ($response->successful()) {
                return true;
            }

            $body = $response->json();
            $message = strtolower((string) (is_array($body) ? ($body['message'] ?? '') : ''));

            // A 400 here usually just means it was already authorised — by an
            // earlier call from this same flow, or by the webhook landing
            // first. That is success, not a failure worth alarming on.
            $alreadyAuthorised = $response->status() === 400 && strpos($message, 'already') !== false;

            if (! $alreadyAuthorised) {
                Log::error('Tamara authorise failed', [
                    'tamara_order_id' => $tamaraOrderId,
                    'status'          => $response->status(),
                    'body'            => $body,
                ]);
            }

            return $alreadyAuthorised;
        } catch (Exception $e) {
            Log::error('Tamara authorise error', ['tamara_order_id' => $tamaraOrderId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /* Payment operations (after authorisation) ---------------------------- */

    /**
     * Capture an authorised order — call when the goods ship. Idempotent:
     * an already-captured order (auto-authorisation / auto-capture / a prior
     * call) reports success without a second capture.
     *
     * Docs: POST /payments/capture
     *
     * @return array{success:bool, status?:string, capture_id?:string, already?:bool, error?:string}
     */
    public function captureOrder(Order $order, Payment $payment): array
    {
        $tamaraId = $this->tamaraOrderId($payment);

        if (! $this->apiToken || ! $tamaraId) {
            return ['success' => false, 'error' => 'Tamara order reference is missing.'];
        }

        $current = $this->fetchStatus($tamaraId);
        $status  = $current['status'] ?? '';

        if ($current && ($current['captured'] ?? false)) {
            return ['success' => true, 'status' => $status, 'already' => true];
        }

        if ($status !== '' && ! in_array($status, ['authorised', 'approved'], true)) {
            return ['success' => false, 'status' => $status, 'error' => "Order is {$status}; it cannot be captured."];
        }

        $shipping = [
            'shipped_at'       => ($order->shipped_at ?? now())->toIso8601String(),
            'shipping_company' => (string) (config('services.tamara.shipping_company') ?: 'Aroma Delivery'),
        ];
        if ($order->tracking_number) {
            $shipping['tracking_number'] = (string) $order->tracking_number;
        }

        $body = [
            'order_id'        => $tamaraId,
            'total_amount'    => $this->money($order->total_amount),
            'shipping_amount' => $this->money($order->shipping_cost),
            'tax_amount'      => $this->money($order->tax_amount),
            'shipping_info'   => $shipping,
        ];
        if ((float) $order->discount_amount > 0) {
            $body['discount_amount'] = $this->money($order->discount_amount);
        }

        return $this->operation('capture', "{$this->baseUrl}/payments/capture", $body, function (array $data) {
            $status = strtolower((string) ($data['status'] ?? ''));

            return [
                'success'    => in_array($status, self::CAPTURED_STATUSES, true),
                'status'     => $status,
                'capture_id' => $data['capture_id'] ?? null,
            ];
        });
    }

    /**
     * Cancel an authorised (not yet captured) order, releasing the hold.
     * Tamara rejects this for `approved` (409) and, once captured, the money
     * has to go back through a refund instead — signalled via needs_refund.
     *
     * Docs: POST /orders/{order_id}/cancel
     *
     * @return array{success:bool, status?:string, needs_refund?:bool, error?:string}
     */
    public function cancelOrder(Order $order, Payment $payment): array
    {
        $tamaraId = $this->tamaraOrderId($payment);

        if (! $this->apiToken || ! $tamaraId) {
            return ['success' => false, 'error' => 'Tamara order reference is missing.'];
        }

        $current = $this->fetchStatus($tamaraId);
        $status  = $current['status'] ?? '';

        if ($status === 'canceled') {
            return ['success' => true, 'status' => 'canceled', 'already' => true];
        }

        if ($current && ($current['captured'] ?? false)) {
            return ['success' => false, 'status' => $status, 'needs_refund' => true, 'error' => 'Order is captured; refund it instead.'];
        }

        $body = [
            'total_amount'    => $this->money($order->total_amount),
            'shipping_amount' => $this->money($order->shipping_cost),
            'tax_amount'      => $this->money($order->tax_amount),
        ];
        if ((float) $order->discount_amount > 0) {
            $body['discount_amount'] = $this->money($order->discount_amount);
        }

        return $this->operation('cancel', "{$this->baseUrl}/orders/{$tamaraId}/cancel", $body, function (array $data) {
            $status = strtolower((string) ($data['status'] ?? ''));

            return ['success' => in_array($status, ['canceled', 'updated'], true), 'status' => $status];
        });
    }

    /**
     * Refund a captured order (full or partial). Refunds go back through
     * Tamara only — never to store credit.
     *
     * Docs: POST /payments/simplified-refund/{order_id}
     *
     * @return array{success:bool, status?:string, refunded?:float, refund_id?:string, error?:string}
     */
    public function refundOrder(Payment $payment, float $amount, string $comment, ?string $merchantRefundId = null): array
    {
        $tamaraId = $this->tamaraOrderId($payment);

        if (! $this->apiToken || ! $tamaraId) {
            return ['success' => false, 'error' => 'Tamara order reference is missing.'];
        }

        $body = [
            'total_amount' => $this->money($amount),
            'comment'      => $comment,
        ];
        if ($merchantRefundId) {
            $body['merchant_refund_id'] = $merchantRefundId;
        }

        return $this->operation('refund', "{$this->baseUrl}/payments/simplified-refund/{$tamaraId}", $body, function (array $data) use ($amount) {
            $status   = strtolower((string) ($data['status'] ?? ''));
            $refunded = $data['refunded_amount']['amount'] ?? $amount;

            return [
                'success'   => in_array($status, ['fully_refunded', 'partially_refunded'], true),
                'status'    => $status,
                'refunded'  => (float) $refunded,
                'refund_id' => $data['refund_id'] ?? null,
            ];
        });
    }

    /**
     * Shared POST + error handling for the operations above.
     *
     * @param array<string,mixed> $body
     * @param callable(array<string,mixed>):array<string,mixed> $interpret
     * @return array<string,mixed>
     */
    private function operation(string $name, string $url, array $body, callable $interpret): array
    {
        try {
            $response = Http::withToken($this->apiToken)->acceptJson()->post($url, $body);
            $data     = $response->json();
            $data     = is_array($data) ? $data : [];

            if (! $response->successful()) {
                $message = (string) ($data['message'] ?? $data['errors'][0]['error_code'] ?? "HTTP {$response->status()}");

                Log::error("Tamara {$name} failed", ['status' => $response->status(), 'body' => $data]);

                return ['success' => false, 'error' => $message];
            }

            $result = $interpret($data);

            if (! ($result['success'] ?? false)) {
                Log::warning("Tamara {$name} returned an unexpected status", ['body' => $data]);
                $result['error'] = $result['error'] ?? 'Unexpected response from Tamara.';
            }

            return $result;
        } catch (Exception $e) {
            Log::error("Tamara {$name} error", ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* Webhooks ------------------------------------------------------------ */

    /**
     * Verifies a Tamara notification. Tamara sends a JWT (HS256) — signed
     * with the merchant's Notification Token — both as the `tamaraToken`
     * query parameter and as `Authorization: Bearer <jwt>`. The raw token is
     * accepted too, for a webhook registered with a static Authorization
     * header. Fails closed: an unset configured token never verifies.
     */
    public function verifyNotificationToken(?string $token): bool
    {
        if ($this->notificationToken === '' || $token === null || $token === '') {
            return false;
        }

        if (hash_equals($this->notificationToken, $token)) {
            return true;
        }

        return $this->verifyJwt($token);
    }

    private function verifyJwt(string $jwt): bool
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode((string) $this->base64UrlDecode($header64), true);

        // Pin the algorithm: never let the token choose "none" or another one.
        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return false;
        }

        $signature = $this->base64UrlDecode($signature64);

        if ($signature === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $header64.'.'.$payload64, $this->notificationToken, true);

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $claims = json_decode((string) $this->base64UrlDecode($payload64), true);

        if (is_array($claims) && isset($claims['exp']) && is_numeric($claims['exp']) && (int) $claims['exp'] < time()) {
            return false;
        }

        return true;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $value   = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Registers the order-notification webhook.
     *
     * Docs: POST /webhooks { type, url, events[] }
     *
     * @param array<int,string> $events
     * @return array{success:bool, webhook_id?:string, error?:string, status?:int}
     */
    public function registerWebhook(string $url, array $events): array
    {
        if (! $this->apiToken) {
            return ['success' => false, 'error' => 'Tamara is not configured.'];
        }

        try {
            $response = Http::withToken($this->apiToken)->acceptJson()
                ->post("{$this->baseUrl}/webhooks", ['type' => 'order', 'url' => $url, 'events' => array_values($events)]);
            $data = $response->json();
            $data = is_array($data) ? $data : [];

            if (! $response->successful()) {
                return ['success' => false, 'status' => $response->status(), 'error' => json_encode($data)];
            }

            return ['success' => true, 'webhook_id' => (string) ($data['webhook_id'] ?? ''), 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed>|null */
    public function retrieveWebhook(string $webhookId): ?array
    {
        if (! $this->apiToken) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiToken)->acceptJson()->get("{$this->baseUrl}/webhooks/{$webhookId}");

            return $response->successful() ? $response->json() : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function deleteWebhook(string $webhookId): bool
    {
        if (! $this->apiToken) {
            return false;
        }

        try {
            return Http::withToken($this->apiToken)->acceptJson()->delete("{$this->baseUrl}/webhooks/{$webhookId}")->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    /* Helpers ------------------------------------------------------------- */

    /** Tamara's own order id for a payment (persisted at checkout / webhook time). */
    public function tamaraOrderId(Payment $payment): ?string
    {
        $id = $payment->reference_number
            ?: (is_array($payment->gateway_response) ? ($payment->gateway_response['order_id'] ?? null) : null);

        return $id ? (string) $id : null;
    }

    /** @return array<string,mixed> */
    private function checkoutPayload(Order $order): array
    {
        [$firstName, $lastName] = $this->splitName($order->customer_name);
        $shipping = is_array($order->shipping_address) ? $order->shipping_address : [];
        $callback = route('payment.callback');
        $phone    = $this->normalizePhone($order->customer_phone) ?? $order->customer_phone;

        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'reference_id' => (string) $item->id,
                'type'         => 'Physical',
                'name'         => $this->itemName($item->product_data),
                'sku'          => (string) ($item->product_data['sku'] ?? $item->product_id),
                'quantity'     => (int) $item->quantity,
                'unit_price'   => $this->money($item->unit_price),
                'total_amount' => $this->money($item->line_total),
            ];
        }

        // Gift wrap and the greeting card are part of what the customer pays
        // (they're in total_amount) but aren't order items — without listing
        // them, items + shipping + tax - discount would fall short of the
        // total Tamara is asked to charge.
        foreach ([
            'gift-wrap'     => ['Gift wrap', (float) $order->gift_wrap_fee],
            'greeting-card' => ['Greeting card', (float) $order->greeting_card_fee],
        ] as $sku => [$label, $fee]) {
            if ($fee > 0) {
                $items[] = [
                    'reference_id' => $order->order_number.'-'.$sku,
                    'type'         => 'Physical',
                    'name'         => $label,
                    'sku'          => strtoupper($sku),
                    'quantity'     => 1,
                    'unit_price'   => $this->money($fee),
                    'total_amount' => $this->money($fee),
                ];
            }
        }

        $payload = [
            'order_reference_id' => $order->order_number,
            'total_amount'       => $this->money($order->total_amount),
            'tax_amount'         => $this->money($order->tax_amount),
            'shipping_amount'    => $this->money($order->shipping_cost),
            'description'        => 'Order '.$order->order_number,
            'country_code'       => 'SA',
            'payment_type'       => 'PAY_BY_INSTALMENTS',
            'locale'             => app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US',
            'items'              => $items,
            'consumer'           => [
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'phone_number' => $phone,
                'email'        => $order->customer_email,
            ],
            'shipping_address' => [
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                // formatted_address is what new (location-code) orders carry;
                // street_address only survives on orders placed before the
                // Saudi National Address cutover.
                'line1'        => $shipping['formatted_address'] ?? $shipping['street_address'] ?? '',
                'city'         => $shipping['city'] ?? '',
                'country_code' => 'SA',
                'phone_number' => $phone,
            ],
            'merchant_url' => [
                'success'      => $callback,
                'failure'      => $callback,
                'cancel'       => $callback,
                // Its own route, not the shared payment.webhook name — that
                // one is wired to MoyasarWebhookController, which doesn't
                // understand Tamara's payload shape or auth scheme.
                'notification' => route('payment.webhook.tamara'),
            ],
        ];

        // Promo/coupon discount — Tamara wants it named and itemised so the
        // customer-facing order summary and the merchant portal reconcile.
        if ((float) $order->discount_amount > 0) {
            $payload['discount'] = [
                'name'   => (string) ($order->promo_code ?: 'Discount'),
                'amount' => $this->money($order->discount_amount),
            ];
        }

        $itemsTotal = array_sum(array_map(function (array $i) {
            return (float) $i['total_amount']['amount'];
        }, $items));
        $expected = $itemsTotal + (float) $order->shipping_cost + (float) $order->tax_amount - (float) $order->discount_amount;

        if (abs($expected - (float) $order->total_amount) > 0.01) {
            Log::warning('Tamara payload total does not reconcile with its parts', [
                'order'    => $order->order_number,
                'total'    => (float) $order->total_amount,
                'computed' => round($expected, 2),
            ]);
        }

        return $payload;
    }

    /**
     * E.164 for a Saudi/Gulf number, or null when it can't be made into one
     * (callers then treat the customer as having no phone).
     */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        if (strpos($digits, '966') === 0 && strlen($digits) === 12) {
            return '+'.$digits;
        }

        // Local Saudi formats: 05XXXXXXXX or 5XXXXXXXX.
        if (strlen($digits) === 10 && strpos($digits, '05') === 0) {
            return '+966'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && $digits[0] === '5') {
            return '+966'.$digits;
        }

        // Some other country code the shopper entered deliberately.
        if (strlen($digits) >= 10 && strlen($digits) <= 15 && strpos((string) $phone, '+') !== false) {
            return '+'.$digits;
        }

        return null;
    }

    /** @return array{0:string,1:string} */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = $parts[0] ?? 'Customer';
        $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;

        return [$first, $last];
    }

    /** @param array<string,mixed>|null $productData */
    private function itemName($productData): string
    {
        $name = is_array($productData) ? ($productData['name'] ?? null) : null;

        return is_array($name) ? (reset($name) ?: 'Item') : (string) ($name ?: 'Item');
    }

    /** @return array{amount:string,currency:string} */
    private function money($value): array
    {
        return ['amount' => number_format((float) $value, 2, '.', ''), 'currency' => 'SAR'];
    }
}
