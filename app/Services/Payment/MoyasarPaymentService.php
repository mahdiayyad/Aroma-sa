<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use Exception;
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
     * Verify webhook signature from Moyasar
     */
    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        // Fail closed: an unset secret must never verify as valid. Without
        // this, a misconfigured deployment (MOYASAR_WEBHOOK_SECRET unset)
        // would silently HMAC every payload with an empty key — and since
        // an attacker can compute hash_hmac('sha256', $body, '', true) too,
        // that's a forgeable signature, not a missing one.
        if ($this->webhookSecret === '' || $this->webhookSecret === null) {
            return false;
        }

        $computed = hash_hmac('sha256', $body, $this->webhookSecret, true);
        $expected = base64_decode($signature);

        return hash_equals($computed, $expected);
    }

    /**
     * Handle payment webhook from Moyasar
     */
    public function handleWebhook(array $data): void
    {
        $event = $data['event'] ?? null;
        $payment = $data['data'] ?? [];

        if (!isset($payment['metadata']['order_id'])) {
            Log::warning('Webhook received with missing order_id');
            return;
        }

        $orderId = $payment['metadata']['order_id'];
        $order = Order::find($orderId);

        if (!$order) {
            Log::warning('Webhook for non-existent order', ['order_id' => $orderId]);
            return;
        }

        // Find or create payment record
        $orderPayment = $order->payment()
            ->where('gateway', 'moyasar')
            ->first();

        if (!$orderPayment) {
            $orderPayment = Payment::create([
                'order_id' => $order->id,
                'gateway' => 'moyasar',
                'method' => $payment['method'] ?? 'card',
                'transaction_id' => $payment['id'] ?? null,
                'reference_number' => $payment['reference_id'] ?? null,
                'amount' => (int) $payment['amount'] / 100, // Convert from fils
                'currency' => $payment['currency'] ?? 'SAR',
                'status' => Payment::STATUS_PENDING,
                'gateway_response' => $payment,
            ]);
        }

        // Handle different webhook events
        switch ($event) {
            case 'invoice.paid':
                $this->handlePaymentSuccess($order, $orderPayment, $payment);
                break;
            case 'invoice.failed':
                $this->handlePaymentFailed($order, $orderPayment, $payment);
                break;
            case 'invoice.expired':
                $this->handlePaymentExpired($order, $orderPayment, $payment);
                break;
            default:
                Log::info('Unhandled webhook event', ['event' => $event]);
        }
    }

    /**
     * Process successful payment. Records the webhook-specific transaction
     * detail here, then routes the "this order is now paid" side effects
     * (status, OrderPaid event) through CheckoutService::markOrderAsPaid —
     * the single source of truth also used by the customer-return callback,
     * so the confirmation email fires exactly once regardless of which path
     * observes the payment first.
     */
    private function handlePaymentSuccess(Order $order, Payment $payment, array $paymentData): void
    {
        try {
            $payment->update([
                'transaction_id' => $paymentData['id'],
                'gateway_response' => $paymentData,
            ]);

            $this->checkout->markOrderAsPaid($order, $payment);

            Log::info('Payment processed successfully', [
                'order_id' => $order->id,
                'transaction_id' => $paymentData['id'],
            ]);
        } catch (Exception $e) {
            Log::error('Error processing payment success', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed(Order $order, Payment $payment, array $paymentData): void
    {
        $payment->update([
            'status' => Payment::STATUS_FAILED,
            'gateway_response' => $paymentData,
        ]);

        $order->update(['status' => Order::STATUS_PENDING]);

        Log::warning('Payment failed', [
            'order_id' => $order->id,
            'error' => $paymentData['error_message'] ?? 'Unknown error',
        ]);

        // Trigger OrderPaymentFailed event
        event(new \App\Events\OrderPaymentFailed($order));
    }

    /**
     * Handle expired payment
     */
    private function handlePaymentExpired(Order $order, Payment $payment, array $paymentData): void
    {
        $payment->update([
            'status' => Payment::STATUS_FAILED,
            'gateway_response' => $paymentData,
        ]);

        $order->update(['status' => Order::STATUS_CANCELLED]);

        Log::info('Payment expired', ['order_id' => $order->id]);
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
