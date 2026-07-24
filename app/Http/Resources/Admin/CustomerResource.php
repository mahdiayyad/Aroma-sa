<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'gender'         => $this->gender,
            'locale'         => $this->locale,
            'loyalty_points' => (int) $this->loyalty_points,
            'is_active'      => (bool) $this->is_active,
            'role'           => $this->role,
            'provider'       => $this->provider,
            'orders_count'   => $this->when(isset($this->orders_count), fn () => (int) $this->orders_count),
            'orders'         => OrderResource::collection($this->whenLoaded('orders')),
            'created_at'     => $this->created_at,
        ];
    }
}
