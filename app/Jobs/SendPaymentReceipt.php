<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\Receipts\PaymentReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues, renders and emails an order's payment receipt. Holds ids rather
 * than models so a deleted order simply ends the job.
 */
class SendPaymentReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Set here, not on the worker: the production worker runs with --tries=1. */
    public $tries = 5;

    /** @var array<int,int> seconds between attempts */
    public $backoff = [60, 300, 900, 3600];

    public $timeout = 120;

    /** @var int */
    public $orderId;

    /** @var int */
    public $paymentId;

    /** @var bool an explicit admin resend, sent even if already emailed */
    public $resend;

    public function __construct(int $orderId, int $paymentId, bool $resend = false)
    {
        $this->orderId = $orderId;
        $this->paymentId = $paymentId;
        $this->resend = $resend;
    }

    public function handle(PaymentReceiptService $receipts): void
    {
        $order = Order::find($this->orderId);
        $payment = Payment::find($this->paymentId);

        if (! $order || ! $payment) {
            Log::warning('Payment receipt job: order or payment no longer exists', [
                'order_id' => $this->orderId,
                'payment_id' => $this->paymentId,
            ]);

            return;
        }

        $receipt = $receipts->issue($order, $payment);

        if ($receipt) {
            $receipts->deliver($receipt, $this->resend);
        }
    }

    public function failed(Throwable $e): void
    {
        PaymentReceipt::query()->where('order_id', $this->orderId)->update([
            'failed_at' => now(),
            'sending_at' => null,
            'last_error' => Str::limit($e->getMessage(), 1000),
        ]);

        Log::error('Payment receipt could not be emailed after all retries', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}
