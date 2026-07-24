<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
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

        return $order;
    }
}
