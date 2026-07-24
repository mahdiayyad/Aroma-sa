<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->getTranslations('name'),
            'sku'            => $this->sku,
            // `attributes` is also Eloquent's internal bag name — read the column explicitly.
            'attributes'     => $this->resource->getAttribute('attributes'),
            'price'          => (float) $this->price,
            'stock_quantity' => (int) $this->stock_quantity,
            'is_active'      => (bool) $this->is_active,
            'sort_order'     => $this->sort_order,
        ];
    }
}
