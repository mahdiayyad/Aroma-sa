<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ReviewRequestMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Payment\TamaraOrderOperations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * The single authority on order-status transitions. Both the admin API and any
 * future automation go through here so the state machine can never be bypassed
 * (e.g. jumping a pending order straight to "delivered").
 */
class OrderStatusService
{
    /** @var array<string,array<int,string>> allowed next statuses per current status */
    private const TRANSITIONS = [
        Order::STATUS_PENDING    => [Order::STATUS_PAID, Order::STATUS_CANCELLED],
        Order::STATUS_PAID       => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
        Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
        Order::STATUS_SHIPPED    => [Order::STATUS_DELIVERED],
        Order::STATUS_DELIVERED  => [],
        Order::STATUS_CANCELLED  => [],
    ];

    /** @var TamaraOrderOperations */
    private $tamara;

    /** @var array<int,array{success:bool,message:string}> payment side-effect results of the last transition */
    private $notes = [];

    public function __construct(TamaraOrderOperations $tamara)
    {
        $this->tamara = $tamara;
    }

    /**
     * What the last transition() did beyond changing the status (e.g. a Tamara
     * capture or refund), for the caller to show the admin. Empty when the
     * order has no gateway follow-up.
     *
     * @return array<int,array{success:bool,message:string}>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /** @return array<int,string> */
    public function allowedNext(Order $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }

    public function canTransition(Order $order, string $to): bool
    {
        return in_array($to, $this->allowedNext($order), true);
    }

    /**
     * Apply a status change and its side effects (timestamps / tracking).
     *
     * @param array{tracking_number?:string} $meta
     * @throws InvalidArgumentException on an illegal transition
     */
    public function transition(Order $order, string $to, array $meta = []): Order
    {
        if (! $this->canTransition($order, $to)) {
            throw new InvalidArgumentException("Cannot move order from {$order->status} to {$to}.");
        }

        $attributes = ['status' => $to];

        if ($to === Order::STATUS_SHIPPED) {
            $attributes['shipped_at'] = now();
            if (! empty($meta['tracking_number'])) {
                $attributes['tracking_number'] = $meta['tracking_number'];
            }
        }

        if ($to === Order::STATUS_DELIVERED) {
            $attributes['delivered_at'] = now();
        }

        $order->update($attributes);

        $this->notes = [];

        // Money movement follows fulfilment: Tamara wants the capture when the
        // goods ship, and a cancelled order must release/refund the customer.
        // Failures are reported, never thrown — a gateway outage must not
        // block the admin's status change (the capture can be retried).
        if ($to === Order::STATUS_SHIPPED && ($note = $this->tamara->capture($order))) {
            $this->notes[] = $note;
        }

        if ($to === Order::STATUS_CANCELLED && ($note = $this->tamara->cancel($order))) {
            $this->notes[] = $note;
        }

        if ($to === Order::STATUS_DELIVERED) {
            $this->requestReview($order);
        }

        return $order;
    }

    /**
     * Asks the customer to review what just arrived, leaving out anything
     * they've already reviewed. Guest orders are skipped because the review
     * form needs an account. Never throws, like the gateway calls above: a
     * mail outage must not block the status change.
     */
    private function requestReview(Order $order): void
    {
        if (! $order->user_id || ! $order->customer_email) {
            return;
        }

        try {
            $products = Product::active()
                ->whereIn('id', $order->items()->pluck('product_id'))
                ->whereNotIn('id', ProductReview::withTrashed()->where('user_id', $order->user_id)->pluck('product_id'))
                ->get();

            if ($products->isNotEmpty()) {
                Mail::to($order->customer_email)->send(new ReviewRequestMail($order, $products));
            }
        } catch (Throwable $e) {
            Log::error('Review-request email failed to send', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
