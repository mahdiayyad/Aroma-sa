<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status,
            'is_paid'          => $this->isPaid(),
            'user_id'          => $this->user_id,
            'is_guest'         => $this->user_id === null,
            'customer_name'    => $this->customer_name,
            'customer_email'   => $this->customer_email,
            'customer_phone'   => $this->customer_phone,
            'billing_address'  => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'subtotal'         => (float) $this->subtotal,
            'discount_amount'  => (float) $this->discount_amount,
            'shipping_cost'    => (float) $this->shipping_cost,
            'tax_amount'       => (float) $this->tax_amount,
            'total_amount'     => (float) $this->total_amount,
            'shipping_method'  => $this->shipping_method,
            'tracking_number'  => $this->tracking_number,
            'shipped_at'       => $this->shipped_at,
            'delivered_at'     => $this->delivered_at,
            'customer_notes'   => $this->customer_notes,
            'internal_notes'   => $this->internal_notes,
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'payments'         => PaymentResource::collection($this->whenLoaded('payment')),
            'allowed_next'     => $this->when(isset($this->allowed_next), $this->allowed_next),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
