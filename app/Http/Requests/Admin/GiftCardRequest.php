<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by auth + admin middleware
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $editing = (bool) $this->route('gift_card');

        return [
            'name.ar'     => ['required', 'string', 'max:255'],
            'name.en'     => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255'],
            'image'       => [$editing ? 'nullable' : 'required', 'image', 'max:2048'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ];
    }
}
