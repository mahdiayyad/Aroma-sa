<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Jobs\SendPaymentReceipt;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueuePaymentReceipt
{
    public function handle(OrderPaid $event): void
    {
        // OrderPaid fires inside markOrderAsPaid()'s transaction: afterCommit()
        // holds the job until the paid state is committed, so a rollback can
        // never leave a receipt for an unpaid order. A queueing failure is
        // logged, never thrown — it must not undo the paid transition.
        try {
            SendPaymentReceipt::dispatch($event->order->id, $event->payment->id)->afterCommit();
        } catch (Throwable $e) {
            Log::error('Failed to queue payment receipt', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
