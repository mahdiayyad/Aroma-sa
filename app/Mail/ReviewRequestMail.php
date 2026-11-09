<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Sent once, when an order is marked delivered (OrderStatusService), linking
 * straight to the review form of each product the customer hasn't reviewed.
 */
class ReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public Collection $products;

    public function __construct(Order $order, Collection $products)
    {
        $this->order = $order;
        $this->products = $products;
        $this->locale($order->locale ?: config('aroma.default_locale', 'ar'));
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(__('emails.review_request.subject', ['order_number' => $this->order->order_number]))
            ->view('emails.review-request')
            ->with([
                'order' => $this->order,
                'products' => $this->products,
                'brand' => config('aroma.brand'),
            ]);
    }
}
