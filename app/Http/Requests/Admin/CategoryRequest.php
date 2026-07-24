<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by auth:sanctum + admin middleware
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'   => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }

    public function rules(): array
    {
        $categoryId = optional($this->route('category'))->id;

        return [
            'name.ar'             => ['required', 'string', 'max:255'],
            'name.en'             => ['required', 'string', 'max:255'],
            'slug'                => ['nullable', 'string', 'max:255'],
            'description.ar'      => ['nullable', 'string'],
            'description.en'      => ['nullable', 'string'],
            'parent_id'           => ['nullable', 'integer', 'exists:categories,id', "not_in:{$categoryId}"],
            'icon'                => ['nullable', 'string', 'max:100'],
            'image'               => ['nullable', 'image', 'max:2048'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],
            'meta_title.ar'       => ['nullable', 'string', 'max:255'],
            'meta_title.en'       => ['nullable', 'string', 'max:255'],
            'meta_description.ar' => ['nullable', 'string', 'max:500'],
            'meta_description.en' => ['nullable', 'string', 'max:500'],
        ];
    }
}
