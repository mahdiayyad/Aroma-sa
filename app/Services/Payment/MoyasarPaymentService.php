<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moyasar Payment Gateway Integration
 * Handles invoice creation, payment processing, and webhook verification
 *
 * Supports: Mada, Visa, Mastercard, Apple Pay
 * BNPL: Tabby, Tamara are separate gateways (see Tabby/TamaraPaymentService).
 */
class MoyasarPaymentService implements PaymentGateway
{
    private string $publishableKey;
    private string $secretKey;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.moyasar.com/v1';
    private CheckoutService $checkout;

    public function key(): string
    {
        return 'moyasar';
    }

    /**
     * Contract adapter: create a Moyasar invoice and normalise the result so
     * the checkout treats every gateway identically.
     */
    public function createCheckout(Order $order): array
    {
        $result = $this->createInvoice($order);

        if (! ($result['success'] ?? false)) {
            return ['success' => false, 'error' => $result['error'] ?? 'Payment could not be started.'];
        }

        return ['success' => true, 'redirect_url' => $result['url'], 'reference' => (string) $result['invoice_id']];
    }

    /**
     * Contract adapter: fetch the invoice and report whether it is paid.
     */
    public function fetchStatus(string $reference): ?array
    {
        $data = $this->getPaymentStatus($reference);

        if (! $data) {
            return null;
        }

        $status = (string) ($data['status'] ?? 'unknown');

        return ['paid' => $status === 'paid', 'status' => $status, 'raw' => $data];
    }

    public function __construct(CheckoutService $checkout)
    {
        $this->publishableKey = (string) config('services.moyasar.publishable_key', '');
        $this->secretKey = (string) config('services.moyasar.secret_key', '');
        $this->webhookSecret = (string) config('services.moyasar.webhook_secret', '');
        $this->checkout = $checkout;
    }

    /**
     * Guard the API calls that actually need credentials. Deliberately not in
     * the constructor: this service is injected into CheckoutController, so
     * throwing on construction would 500 the cart-review / address / payment
     * pages even though they never call the gateway. Failing here instead keeps
     * those pages working and surfaces a clean error only at payment time.
     */
    private function ensureConfigured(): void
    {
        if (!$this->secretKey) {
            throw new Exception('Moyasar API keys not configured');
        }
    }

    /**
     * Create a payment invoice for an order
     * Returns client token for frontend payment form
     */
    public function createInvoice(Order $order): array
    {
        try {
            $this->ensureConfigured();

            $payload = [
                'amount' => (int) round($order->total_amount * 100), // Convert to fils
                'currency' => 'SAR',
                'description' => sprintf('Order %s - Aroma', $order->order_number),
                'reference_id' => $order->order_number,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'callback_url' => route('payment.callback'),
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ];

            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/invoices", $payload);

            if (!$response->successful()) {
                Log::error('Moyasar invoice creation failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                throw new Exception('Failed to create payment invoice');
            }

            $invoiceData = $response->json();

            return [
                'success' => true,
                'invoice_id' => $invoiceData['id'],
                'url' => $invoiceData['url'],
                'client_token' => $invoiceData['token'] ?? null,
                'publishable_key' => $this->publishableKey,
            ];
        } catch (Exception $e) {
            Log::error('Moyasar service error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Moyasar webhook authentication: Moyasar puts the endpoint's Secret Token
     * (set in Dashboard → Settings → Webhooks) in the JSON body as
     * `secret_token` — it does not sign requests. Constant-time comparison;
     * fails closed when no secret is configured.
     *
     * @param mixed $token
     */
    public function verifyWebhookToken($token): bool
    {
        if (! is_string($token) || $token === '' || $this->webhookSecret === '') {
            return false;
        }

        return hash_equals($this->webhookSecret, $token);
    }

    /**
     * Handle an authenticated Moyasar webhook (`payment_*` events).
     *
     * Only the payment id is taken from the payload. The payment itself is
     * fetched from Moyasar's API and that copy decides what happens, after
     * its invoice, amount and currency are checked against our own order.
     * Every branch is safe to repeat: Moyasar redelivers until it gets a 2xx.
     *
     * @param array<string,mixed> $event
     * @throws GatewayUnavailableException when Moyasar can't be reached (retry later)
     */
    public function handleWebhook(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $paymentId = (string) data_get($event, 'data.id', '');

        if (strpos($type, 'payment_') !== 0 || preg_match('/^[A-Za-z0-9_-]{1,100}$/', $paymentId) !== 1) {
            Log::info('Moyasar webhook ignored', ['type' => $type, 'event_id' => $event['id'] ?? null]);

            return;
        }

        $remote = $this->fetchPayment($paymentId);
        if ($remote === null) {
            Log::warning('Moyasar webhook for a payment Moyasar does not know', ['type' => $type, 'moyasar_payment' => $paymentId]);

            return;
        }

        $payment = $this->localPaymentFor($remote);
        if (! $payment || ! $payment->order) {
            Log::warning('Moyasar webhook for a payment with no matching order', [
                'type' => $type,
                'moyasar_payment' => $paymentId,
                'invoice' => $remote['invoice_id'] ?? null,
            ]);

            return;
        }

        // The status Moyasar reports now, not the event name: deliveries can
        // arrive late or out of order (a "paid" event for a since-refunded payment).
        switch ((string) ($remote['status'] ?? '')) {
            case 'paid':
            case 'captured':
                $this->handlePaymentSuccess($payment->order, $payment, $remote);
                break;
            case 'failed':
                $this->handlePaymentFailed($payment->order, $payment, $remote);
                break;
            case 'refunded':
                $this->handlePaymentRefunded($payment, $remote);
                break;
            case 'voided':
                $this->handlePaymentVoided($payment->order, $payment, $remote);
                break;
            default:
                // initiated / authorized / verified: nothing to finalise yet
                // (invoice payments are captured automatically).
                Log::info('Moyasar webhook: payment not final yet', [
                    'order_id' => $payment->order_id,
                    'moyasar_payment' => $paymentId,
                    'status' => $remote['status'] ?? null,
                ]);
        }
    }

    /**
     * The payment as Moyasar reports it. Null when Moyasar doesn't know it
     * (404 — e.g. a test-mode id sent to live keys); throws when Moyasar is
     * unreachable or failing, so the webhook is retried.
     *
     * @return array<string,mixed>|null
     */
    public function fetchPayment(string $paymentId): ?array
    {
        $this->ensureConfigured();

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(15)
                ->get("{$this->baseUrl}/payments/".rawurlencode($paymentId));
        } catch (Exception $e) {
            throw new GatewayUnavailableException('Moyasar API unreachable: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new GatewayUnavailableException('Moyasar API returned HTTP '.$response->status());
        }

        return $response->json();
    }

    /**
     * Our payment record for a Moyasar payment: by its invoice (the id saved
     * as reference_number when checkout started), else by a payment id we
     * already recorded. Never created from webhook data.
     *
     * @param array<string,mixed> $remote
     */
    private function localPaymentFor(array $remote): ?Payment
    {
        $invoiceId = (string) ($remote['invoice_id'] ?? '');

        if ($invoiceId !== '') {
            $payment = Payment::query()
                ->where('gateway', 'moyasar')
                ->where('reference_number', $invoiceId)
                ->latest('id')
                ->first();

            if ($payment) {
                return $payment;
            }
        }

        return Payment::query()
            ->where('gateway', 'moyasar')
            ->where('transaction_id', (string) ($remote['id'] ?? ''))
            ->first();
    }

    /**
     * Whether Moyasar's amount (in halalas) and currency are exactly what the
     * order charged. Anything else is never treated as payment of this order.
     *
     * @param array<string,mixed> $remote
     */
    private function matchesOrder(Order $order, Payment $payment, array $remote): bool
    {
        $expectedAmount = (int) round((float) $order->total_amount * 100);
        $expectedCurrency = strtoupper((string) ($payment->currency ?: config('aroma.currency.code', 'SAR')));

        return is_numeric($remote['amount'] ?? null)
            && (int) $remote['amount'] === $expectedAmount
            && strtoupper((string) ($remote['currency'] ?? '')) === $expectedCurrency;
    }

    /**
     * Paid: record Moyasar's payment, then route the paid transition through
     * CheckoutService::markOrderAsPaid — the single, locked source of truth
     * shared with the customer-return callback. It fires OrderPaid (order
     * confirmation + payment receipt) exactly once, however many times this
     * webhook is delivered.
     *
     * @param array<string,mixed> $remote
     */
    private function handlePaymentSuccess(Order $order, Payment $payment, array $remote): void
    {
        if (! $this->matchesOrder($order, $payment, $remote)) {
            Log::critical('Moyasar payment does not match its order — not marked paid', [
                'order_id' => $order->id,
                'moyasar_payment' => $remote['id'] ?? null,
                'paid_amount' => $remote['amount'] ?? null,
                'paid_currency' => $remote['currency'] ?? null,
                'expected_amount' => (int) round((float) $order->total_amount * 100),
            ]);

            return;
        }

        $payment->update([
            'transaction_id' => (string) $remote['id'],
            'gateway_response' => $this->withRemote($payment, $remote),
        ]);

        $this->checkout->markOrderAsPaid($order, $payment);

        Log::info('Moyasar payment confirmed by webhook', [
            'order_id' => $order->id,
            'moyasar_payment' => $remote['id'],
        ]);
    }

    /**
     * A declined attempt. Ignored once the order is paid (a later card may
     * have succeeded on the same invoice). The customer is told once per
     * failed Moyasar payment, not once per redelivery.
     *
     * @param array<string,mixed> $remote
     */
    private function handlePaymentFailed(Order $order, Payment $payment, array $remote): void
    {
        $alreadyRecorded = DB::transaction(function () use ($order, $payment, $remote): ?bool {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();
            $order->refresh();

            if (! $locked || $order->isPaid() || $locked->isPaid()) {
                return null;
            }

            $duplicate = $locked->status === Payment::STATUS_FAILED
                && $locked->transaction_id === (string) $remote['id'];

            if (! $duplicate) {
                $locked->update([
                    'status' => Payment::STATUS_FAILED,
                    'transaction_id' => (string) $remote['id'],
                    'gateway_response' => $this->withRemote($locked, $remote),
                ]);
            }

            return $duplicate;
        });

        if ($alreadyRecorded !== false) {
            return;
        }

        Log::warning('Moyasar payment failed', [
            'order_id' => $order->id,
            'moyasar_payment' => $remote['id'],
            'reason' => data_get($remote, 'source.message'),
        ]);

        event(new \App\Events\OrderPaymentFailed($order));
    }

    /**
     * A refund made on Moyasar (e.g. from its dashboard): keep our record in
     * step. The order status is left to the admin.
     *
     * @param array<string,mixed> $remote
     */
    private function handlePaymentRefunded(Payment $payment, array $remote): void
    {
        $refunded = round(((int) ($remote['refunded'] ?? 0)) / 100, 2);

        $payment->update([
            'status' => $refunded >= (float) $payment->amount ? Payment::STATUS_REFUNDED : $payment->status,
            'refunded_amount' => $refunded,
            'refunded_at' => $payment->refunded_at ?? now(),
            'gateway_response' => $this->withRemote($payment, $remote),
        ]);

        Log::info('Moyasar refund recorded', ['order_id' => $payment->order_id, 'refunded' => $refunded]);
    }

    /**
     * A voided (released) authorisation: only recorded while unpaid.
     *
     * @param array<string,mixed> $remote
     */
    private function handlePaymentVoided(Order $order, Payment $payment, array $remote): void
    {
        if ($order->isPaid() || $payment->isPaid()) {
            Log::warning('Moyasar voided a payment for an order already marked paid', ['order_id' => $order->id]);

            return;
        }

        $payment->update([
            'status' => Payment::STATUS_CANCELLED,
            'gateway_response' => $this->withRemote($payment, $remote),
        ]);
    }

    /**
     * Moyasar's payment merged over what we already keep (so the receipt can
     * read its card `source`), minus nothing secret — Moyasar's payment
     * object carries a masked card number only.
     *
     * @param array<string,mixed> $remote
     * @return array<string,mixed>
     */
    private function withRemote(Payment $payment, array $remote): array
    {
        $existing = is_array($payment->gateway_response) ? $payment->gateway_response : [];

        return array_merge($existing, $remote);
    }

    /**
     * Get payment status from Moyasar
     */
    public function getPaymentStatus(string $invoiceId): ?array
    {
        try {
            $this->ensureConfigured();

            $response = Http::withBasicAuth($this->secretKey, '')
                ->get("{$this->baseUrl}/invoices/{$invoiceId}");

            return $response->successful() ? $response->json() : null;
        } catch (Exception $e) {
            Log::error('Failed to fetch payment status', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(Payment $payment, float $amount = null): array
    {
        try {
            $this->ensureConfigured();

            if (!$payment->transaction_id) {
                return ['success' => false, 'error' => 'No transaction ID found'];
            }

            $refundAmount = $amount ?? $payment->amount;

            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/payments/{$payment->transaction_id}/refund", [
                    'amount' => (int) round($refundAmount * 100),
                ]);

            if ($response->successful()) {
                $refundData = $response->json();

                $payment->update([
                    'status' => Payment::STATUS_REFUNDED,
                    'refunded_amount' => $refundAmount,
                    'refunded_at' => now(),
                ]);

                Log::info('Payment refunded', [
                    'payment_id' => $payment->id,
                    'refund_id' => $refundData['id'] ?? null,
                ]);

                return ['success' => true, 'refund_id' => $refundData['id'] ?? null];
            }

            Log::error('Refund failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
            ]);

            return ['success' => false, 'error' => 'Refund processing failed'];
        } catch (Exception $e) {
            Log::error('Refund exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
