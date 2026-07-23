<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail
{
    public function handle(OrderPaid $event): void
    {
        Mail::to($event->order->customer_email)
            ->queue(new OrderConfirmationMail($event->order));
    }
}
