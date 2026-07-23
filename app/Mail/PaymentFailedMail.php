<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(__('emails.payment_failed.subject', ['order_number' => $this->order->order_number]))
            ->view('emails.payment-failed')
            ->with([
                'order' => $this->order,
                'brand' => config('aroma.brand'),
            ]);
    }
}
