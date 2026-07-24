<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_variants'     => $this->boolean('has_variants'),
            'is_active'        => $this->boolean('is_active'),
            'is_featured'      => $this->boolean('is_featured'),
            'is_new_arrival'   => $this->boolean('is_new_arrival'),
            'is_gift_eligible' => $this->boolean('is_gift_eligible'),
        ]);
    }

    public function rules(): array
    {
        $productId = optional($this->route('product'))->id;

        return [
            'name.ar'           => ['required', 'string', 'max:255'],
            'name.en'           => ['required', 'string', 'max:255'],
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'brand_id'          => ['nullable', 'integer', 'exists:brands,id'],
            'slug'              => ['nullable', 'string', 'max:255'],
            'short_description.ar' => ['nullable', 'string'],
            'short_description.en' => ['nullable', 'string'],
            'description.ar'    => ['nullable', 'string'],
            'description.en'    => ['nullable', 'string'],
            'sku'               => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'base_price'        => ['required', 'numeric', 'min:0'],
            'compare_at_price'  => ['nullable', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'stock_quantity'    => ['required', 'integer', 'min:0'],
            'has_variants'      => ['boolean'],
            'scent_family'      => ['nullable', 'string', 'max:100'],
            'is_active'         => ['boolean'],
            'is_featured'       => ['boolean'],
            'is_new_arrival'    => ['boolean'],
            'is_gift_eligible'  => ['boolean'],
            'images'            => ['nullable', 'array'],
            'images.*'          => ['image', 'max:2048'],
            'meta_title.ar'     => ['nullable', 'string', 'max:255'],
            'meta_title.en'     => ['nullable', 'string', 'max:255'],
            'meta_description.ar' => ['nullable', 'string', 'max:500'],
            'meta_description.en' => ['nullable', 'string', 'max:500'],
        ];
    }
}
