<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BrandResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->getTranslations('name'),
            'description'      => $this->getTranslations('description'),
            'slug'             => $this->slug,
            'logo'             => $this->logo,
            'logo_url'         => $this->logo ? Storage::disk('public')->url($this->logo) : null,
            'is_active'        => (bool) $this->is_active,
            'is_featured'      => (bool) $this->is_featured,
            'meta_title'       => $this->getTranslations('meta_title'),
            'meta_description' => $this->getTranslations('meta_description'),
            'products_count'   => $this->when(isset($this->products_count), fn () => (int) $this->products_count),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
