<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_default' => $this->boolean('is_default')]);
    }

    public function rules(): array
    {
        return [
            'label'           => ['nullable', 'string', 'max:60'],
            'recipient_name'  => ['required', 'string', 'max:100'],
            'phone'           => ['required', 'string', 'regex:/^(\+9665|05)\d{8}$/'],
            'street_address'  => ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:100'],
            'region'          => ['required', 'string', 'max:100'],
            'postal_code'     => ['nullable', 'string', 'max:20'],
            'is_default'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('auth_ui.validation.phone'),
        ];
    }
}
