<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Services\Payment\TamaraOrderOperations;
use InvalidArgumentException;

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

        return $order;
    }
}
