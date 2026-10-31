<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously from the refund services' own success path
 * (TamaraOrderOperations::doRefund(), MoyasarPaymentService::refundPayment())
 * right after a refund actually completes — never queued, no idempotency
 * table needed, since each is dispatched from exactly one synchronous admin
 * action with no racing trigger (unlike the payment receipt, which webhook
 * and customer-return paths can both reach).
 */
class RefundProcessedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public float $refundAmount;
    public bool $shippingDeducted;

    public function __construct(Order $order, float $refundAmount, bool $shippingDeducted)
    {
        $this->order = $order;
        $this->refundAmount = $refundAmount;
        $this->shippingDeducted = $shippingDeducted;
        $this->locale($order->locale ?: config('aroma.default_locale', 'ar'));
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(__('emails.refund_processed.subject', ['order_number' => $this->order->order_number]))
            ->view('emails.refund-processed')
            ->with([
                'order' => $this->order,
                'brand' => config('aroma.brand'),
                'refundAmount' => $this->refundAmount,
                'shippingDeducted' => $this->shippingDeducted,
            ]);
    }
}
