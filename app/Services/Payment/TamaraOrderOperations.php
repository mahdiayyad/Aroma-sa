<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;

/**
 * What happens on Tamara's side (and to our Payment record) when an order
 * moves through fulfilment: capture on shipment, cancel or refund on
 * cancellation, and manual partial refunds from the admin.
 *
 * Every method returns null when the order has nothing to do with Tamara (no
 * paid Tamara payment), otherwise a {success, message} pair the admin can be
 * shown. Nothing here throws — a Tamara outage must never stop an admin from
 * moving an order along; the failure is reported and can be retried.
 */
class TamaraOrderOperations
{
    /** @var TamaraPaymentService */
    private $tamara;

    public function __construct(TamaraPaymentService $tamara)
    {
        $this->tamara = $tamara;
    }

    public function paymentFor(Order $order): ?Payment
    {
        return $order->payment()->where('gateway', 'tamara')->latest('id')->first();
    }

    /**
     * Capture on shipment. A payment that is already captured (Tamara
     * auto-authorisation / auto-capture, or an earlier call) is left alone.
     *
     * @return array{success:bool,message:string}|null
     */
    public function capture(Order $order): ?array
    {
        $payment = $this->paymentFor($order);

        if (! $payment || ! $payment->isPaid()) {
            return null;
        }

        if ($payment->status === Payment::STATUS_CAPTURED) {
            return $this->ok('admin.tamara.already_captured');
        }

        $result = $this->tamara->captureOrder($order, $payment);

        if (! ($result['success'] ?? false)) {
            return $this->fail('admin.tamara.capture_failed', $result);
        }

        $payment->update([
            'status'           => Payment::STATUS_CAPTURED,
            'gateway_response' => $this->withEntry($payment, 'capture', [
                'capture_id' => $result['capture_id'] ?? null,
                'at'         => now()->toIso8601String(),
            ]),
        ]);

        return $this->ok('admin.tamara.captured');
    }

    /**
     * Order cancelled: release the hold if it was only authorised, refund in
     * full if the money has already been captured. Refunds go back through
     * Tamara only.
     *
     * @return array{success:bool,message:string}|null
     */
    public function cancel(Order $order): ?array
    {
        $payment = $this->paymentFor($order);

        if (! $payment || ! $payment->isPaid()) {
            return null;
        }

        if ($payment->status === Payment::STATUS_CAPTURED) {
            return $this->refundRemaining($order, $payment, 'admin.tamara.cancel_refunded');
        }

        $result = $this->tamara->cancelOrder($order, $payment);

        if ($result['success'] ?? false) {
            $payment->update([
                'status'           => Payment::STATUS_CANCELLED,
                'gateway_response' => $this->withEntry($payment, 'cancel', ['at' => now()->toIso8601String()]),
            ]);

            return $this->ok('admin.tamara.cancelled');
        }

        // Captured meanwhile (auto-capture) — the only way to return the money is a refund.
        if ($result['needs_refund'] ?? false) {
            $payment->update(['status' => Payment::STATUS_CAPTURED]);

            return $this->refundRemaining($order, $payment, 'admin.tamara.cancel_refunded');
        }

        return $this->fail('admin.tamara.cancel_failed', $result);
    }

    /**
     * Manual (possibly partial) refund from the admin order page.
     *
     * @return array{success:bool,message:string}
     */
    public function refund(Order $order, float $amount, string $comment): array
    {
        $payment = $this->paymentFor($order);

        if (! $payment || $payment->status !== Payment::STATUS_CAPTURED) {
            return $this->fail('admin.tamara.refund_not_allowed');
        }

        $remaining = $this->remaining($payment);
        $amount    = round($amount, 2);

        if ($amount <= 0 || $amount > $remaining + 0.001) {
            return $this->fail('admin.tamara.refund_invalid', [], ['max' => number_format($remaining, 2)]);
        }

        return $this->doRefund($order, $payment, $amount, $comment, 'admin.tamara.refunded');
    }

    /**
     * Reflects a refund Tamara reports through the webhook (a refund started
     * in the Tamara Partner Portal, or the echo of one we issued ourselves —
     * hence "never lower what we already recorded").
     *
     * @param float|null $reportedAmount refunded amount if the notification carries one
     */
    public function syncRefundFromTamara(Payment $payment, ?float $reportedAmount): void
    {
        $tamaraId = $this->tamara->tamaraOrderId($payment);
        $status   = $tamaraId ? (($this->tamara->fetchStatus($tamaraId)['status'] ?? '')) : '';

        $refunded = (float) $payment->refunded_amount;

        if ($reportedAmount !== null) {
            $refunded = max($refunded, min($reportedAmount, (float) $payment->amount));
        }

        if ($status === 'fully_refunded') {
            $refunded = (float) $payment->amount;
        }

        $fully = $refunded >= (float) $payment->amount - 0.009;

        $payment->update([
            'refunded_amount' => $refunded,
            'refunded_at'     => $payment->refunded_at ?: now(),
            'status'          => $fully ? Payment::STATUS_REFUNDED : $payment->status,
        ]);
    }

    /* Internals ----------------------------------------------------------- */

    /** @return array{success:bool,message:string} */
    private function refundRemaining(Order $order, Payment $payment, string $successKey): array
    {
        $remaining = $this->remaining($payment);

        if ($remaining <= 0.009) {
            return $this->ok($successKey);
        }

        return $this->doRefund($order, $payment, $remaining, 'Order '.$order->order_number.' cancelled', $successKey);
    }

    /** @return array{success:bool,message:string} */
    private function doRefund(Order $order, Payment $payment, float $amount, string $comment, string $successKey): array
    {
        $result = $this->tamara->refundOrder(
            $payment,
            $amount,
            $comment,
            $order->order_number.'-R'.now()->format('YmdHis')
        );

        if (! ($result['success'] ?? false)) {
            return $this->fail('admin.tamara.refund_failed', $result);
        }

        $refundedNow = (float) ($result['refunded'] ?? $amount);
        $total       = round((float) $payment->refunded_amount + $refundedNow, 2);
        $fully       = $total >= (float) $payment->amount - 0.009;

        $payment->update([
            'refunded_amount'  => $total,
            'refunded_at'      => now(),
            'status'           => $fully ? Payment::STATUS_REFUNDED : Payment::STATUS_CAPTURED,
            'gateway_response' => $this->withEntry($payment, 'refund_'.now()->format('YmdHis'), [
                'refund_id' => $result['refund_id'] ?? null,
                'amount'    => $refundedNow,
                'comment'   => $comment,
            ]),
        ]);

        return $this->ok($successKey, ['amount' => number_format($refundedNow, 2)]);
    }

    private function remaining(Payment $payment): float
    {
        return round((float) $payment->amount - (float) $payment->refunded_amount, 2);
    }

    /** @return array<string,mixed> */
    private function withEntry(Payment $payment, string $key, array $entry): array
    {
        $existing = is_array($payment->gateway_response) ? $payment->gateway_response : [];
        $existing[$key] = $entry;

        return $existing;
    }

    /**
     * @param array<string,mixed> $replace
     * @return array{success:bool,message:string}
     */
    private function ok(string $key, array $replace = []): array
    {
        return ['success' => true, 'message' => __($key, $replace)];
    }

    /**
     * @param array<string,mixed> $result   the failed gateway result (for its error text)
     * @param array<string,mixed> $replace
     * @return array{success:bool,message:string}
     */
    private function fail(string $key, array $result = [], array $replace = []): array
    {
        $replace['error'] = (string) ($result['error'] ?? '');

        return ['success' => false, 'message' => __($key, $replace)];
    }
}
