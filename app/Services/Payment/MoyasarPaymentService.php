<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moyasar Payment Gateway Integration
 * Handles invoice creation, payment processing, and webhook verification
 *
 * Supports: Mada, Visa, Mastercard, Apple Pay
 * BNPL: Tabby, Tamara (separate integrations)
 */
class MoyasarPaymentService
{
    private string $publishableKey;
    private string $secretKey;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.moyasar.com/v1';

    public function __construct()
    {
        $this->publishableKey = config('services.moyasar.publishable_key', '');
        $this->secretKey = config('services.moyasar.secret_key', '');
        $this->webhookSecret = config('services.moyasar.webhook_secret', '');

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
     * Process successful payment
     */
    private function handlePaymentSuccess(Order $order, Payment $payment, array $paymentData): void
    {
        try {
            // Update payment status
            $payment->update([
                'status' => Payment::STATUS_CAPTURED,
                'transaction_id' => $paymentData['id'],
                'gateway_response' => $paymentData,
            ]);

            // Update order status
            $order->update(['status' => Order::STATUS_PAID]);

            // Trigger OrderPaid event (for notifications, inventory, etc)
            event(new \App\Events\OrderPaid($order, $payment));

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
