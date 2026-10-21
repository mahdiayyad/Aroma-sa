<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PaymentReceipt;
use Illuminate\Mail\Mailable;

/**
 * Sent synchronously from the SendPaymentReceipt job, which owns retries and
 * delivery state — so this mailable is deliberately not ShouldQueue.
 */
class PaymentReceiptMail extends Mailable
{
    /** @var PaymentReceipt */
    public $receipt;

    /** @var string */
    private $pdf;

    public function __construct(PaymentReceipt $receipt, string $pdf)
    {
        $this->receipt = $receipt;
        $this->pdf = $pdf;
        $this->locale($receipt->locale);
    }

    public function build()
    {
        $order = $this->receipt->order;

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(__('receipts.email.subject', ['order_number' => $order->order_number]))
            ->view('emails.payment-receipt')
            ->with([
                'order' => $order,
                'receipt' => $this->receipt,
                'brand' => config('aroma.brand'),
            ])
            ->attachData($this->pdf, $this->receipt->downloadName(), ['mime' => 'application/pdf']);
    }
}
