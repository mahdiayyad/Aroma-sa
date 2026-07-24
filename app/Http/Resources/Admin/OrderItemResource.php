<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_data'       => $this->product_data, // snapshot { name, image, sku }
            'variant_data'       => $this->variant_data,
            'unit_price'         => (float) $this->unit_price,
            'quantity'           => (int) $this->quantity,
            'line_total'         => (float) $this->line_total,
        ];
    }
}
