<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'category_id'       => $this->category_id,
            'brand_id'          => $this->brand_id,
            'name'              => $this->getTranslations('name'),
            'short_description' => $this->getTranslations('short_description'),
            'description'       => $this->getTranslations('description'),
            'slug'              => $this->slug,
            'sku'               => $this->sku,
            'base_price'        => (float) $this->base_price,
            'compare_at_price'  => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'currency'          => $this->currency,
            'stock_quantity'    => (int) $this->stock_quantity,
            'has_variants'      => (bool) $this->has_variants,
            'scent_family'      => $this->scent_family,
            'is_active'         => (bool) $this->is_active,
            'is_featured'       => (bool) $this->is_featured,
            'is_new_arrival'    => (bool) $this->is_new_arrival,
            'is_gift_eligible'  => (bool) $this->is_gift_eligible,
            // Computed helpers for convenience.
            'on_sale'           => $this->isOnSale(),
            'discount_percent'  => $this->discountPercent(),
            'in_stock'          => $this->inStock(),
            'meta_title'        => $this->getTranslations('meta_title'),
            'meta_description'  => $this->getTranslations('meta_description'),
            'category'          => CategoryResource::make($this->whenLoaded('category')),
            'brand'             => BrandResource::make($this->whenLoaded('brand')),
            'variants'          => ProductVariantResource::collection($this->whenLoaded('variants')),
            'images'            => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
