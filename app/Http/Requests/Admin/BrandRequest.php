<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        return [
            'name.ar'             => ['required', 'string', 'max:255'],
            'name.en'             => ['required', 'string', 'max:255'],
            'slug'                => ['nullable', 'string', 'max:255'],
            'description.ar'      => ['nullable', 'string'],
            'description.en'      => ['nullable', 'string'],
            'logo'                => ['nullable', 'image', 'max:2048'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],
            'meta_title.ar'       => ['nullable', 'string', 'max:255'],
            'meta_title.en'       => ['nullable', 'string', 'max:255'],
            'meta_description.ar' => ['nullable', 'string', 'max:500'],
            'meta_description.en' => ['nullable', 'string', 'max:500'],
        ];
    }
}
