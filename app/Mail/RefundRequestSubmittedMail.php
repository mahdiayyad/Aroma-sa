<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public RefundRequest $refundRequest;

    public function __construct(RefundRequest $refundRequest)
    {
        $this->refundRequest = $refundRequest;
    }

    public function build()
    {
        $order = $this->refundRequest->order;

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Refund request — order '.$order->order_number)
            ->view('emails.refund-request-submitted')
            ->with([
                'refundRequest' => $this->refundRequest,
                'order' => $order,
                'brand' => config('aroma.brand'),
            ]);
    }
}
