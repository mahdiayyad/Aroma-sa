<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaymentFailed;
use App\Mail\PaymentFailedMail;
use Illuminate\Support\Facades\Mail;

class SendPaymentFailedEmail
{
    public function handle(OrderPaymentFailed $event): void
    {
        Mail::to($event->order->customer_email)
            ->queue(new PaymentFailedMail($event->order));
    }
}
