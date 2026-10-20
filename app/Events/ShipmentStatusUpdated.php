<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AramexShipment;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once each time an order's Aramex shipping status actually changes
 * (repeated or older tracking events don't fire it).
 *
 * Register listeners in EventServiceProvider::$listen, e.g. to email the
 * customer when $shipment->shipping_status becomes
 * AramexShipment::STATUS_OUT_FOR_DELIVERY or STATUS_DELIVERED.
 */
class ShipmentStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var Order */
    public $order;

    /** @var AramexShipment */
    public $shipment;

    /** @var string|null status before this update (null for a newly created shipment) */
    public $previousStatus;

    public function __construct(Order $order, AramexShipment $shipment, ?string $previousStatus = null)
    {
        $this->order = $order;
        $this->shipment = $shipment;
        $this->previousStatus = $previousStatus;
    }
}
