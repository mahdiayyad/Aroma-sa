<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmationEmail
{
    public function handle(OrderPaid $event): void
    {
        // OrderPaid fires from inside CheckoutService::markOrderAsPaid()'s own
        // DB transaction, on both the customer-return callback path (no
        // try/catch around that call) and the gateway-webhook path (already
        // try/catch-guarded by its caller). Queueing mail is just a `jobs`
        // table insert, but if it ever throws, guarding it here — the one
        // choke point both paths funnel through — is what keeps that failure
        // from propagating up and rolling back the paid-transition itself.
        try {
            Mail::to($event->order->customer_email)
                ->queue(new OrderConfirmationMail($event->order));
        } catch (Throwable $e) {
            Log::error('Failed to queue order confirmation email', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
