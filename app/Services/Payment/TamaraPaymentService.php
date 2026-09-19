<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tamara — "Buy Now, Pay Later" (pay in instalments / pay later). Uses the
 * Tamara Checkout API: create a checkout session, redirect the buyer to the
 * returned checkout_url, then verify the order on return.
 *
 * Docs: https://docs.tamara.co/  (Checkout Session)
 */
class TamaraPaymentService implements PaymentGateway
{
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

    public function fetchStatus(string $reference): ?array
    {
        $data = $this->getOrder($reference);

        if (! $data) {
            return null;
        }

        $status = strtolower((string) ($data['status'] ?? ''));

        // Tamara treats "approved" as a risk pre-approval only — the order is
        // not actually confirmed, and Tamara auto-cancels it after a short
        // window, unless the merchant explicitly authorises it. Do that here
        // so a shopper returning from Tamara (or the webhook, whichever gets
        // there first) is the one call that actually locks the sale in.
        // Idempotent on Tamara's side, so re-authorising on every check is safe.
        if ($status === 'approved') {
            $this->authoriseOrder((string) ($data['order_id'] ?? $reference));
            $refetched = $this->getOrder($reference);
            if ($refetched) {
                $data   = $refetched;
                $status = strtolower((string) ($data['status'] ?? $status));
            }
        }

        return [
            'paid'   => in_array($status, ['approved', 'authorised', 'fully_captured', 'partially_captured'], true),
            'status' => $status,
            'raw'    => $data,
        ];
    }

    /** @return array<string,mixed>|null */
    private function getOrder(string $reference): ?array
    {
        if (! $this->apiToken) {
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
     * Without this call, Tamara auto-voids the order after its approval
     * window expires and the merchant never gets paid despite the shopper
     * having completed checkout on Tamara's side.
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

    /**
     * Verifies a Tamara IPN webhook call. Tamara authenticates its own
     * notification requests with the token you set for the webhook URL in
     * the merchant dashboard — sent as a bearer token; a `?token=` query
     * param is accepted too, in case the URL was registered that way instead.
     * Fails closed: an unset configured token never verifies as valid.
     */
    public function verifyNotificationToken(?string $token): bool
    {
        if ($this->notificationToken === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($this->notificationToken, $token);
    }

    /** @return array<string,mixed> */
    private function checkoutPayload(Order $order): array
    {
        [$firstName, $lastName] = $this->splitName($order->customer_name);
        $shipping = is_array($order->shipping_address) ? $order->shipping_address : [];
        $callback = route('payment.callback');

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

        return [
            'order_reference_id' => $order->order_number,
            'total_amount'       => $this->money($order->total_amount),
            'description'        => 'Order '.$order->order_number,
            'country_code'       => 'SA',
            'payment_type'       => 'PAY_BY_INSTALMENTS',
            'locale'             => app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US',
            'items'              => $items,
            'consumer'           => [
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'phone_number' => $order->customer_phone,
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
                'phone_number' => $order->customer_phone,
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
