<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'parent_id'        => $this->parent_id,
            'name'             => $this->getTranslations('name'),
            'description'      => $this->getTranslations('description'),
            'slug'             => $this->slug,
            'icon'             => $this->icon,
            'image'            => $this->image,
            'image_url'        => $this->image ? Storage::disk('public')->url($this->image) : null,
            'sort_order'       => $this->sort_order,
            'is_active'        => (bool) $this->is_active,
            'is_featured'      => (bool) $this->is_featured,
            'meta_title'       => $this->getTranslations('meta_title'),
            'meta_description' => $this->getTranslations('meta_description'),
            'products_count'   => $this->when(isset($this->products_count), fn () => (int) $this->products_count),
            'children'         => self::collection($this->whenLoaded('children')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
