<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\LocationCode;
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
            // The actual address (street/city/region/coordinates) is resolved
            // server-side from this code by AddressController via
            // LocationLookupService — not collected as free text anymore.
            'location_code'   => ['required', 'string', new LocationCode()],
            'is_default'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('auth_ui.validation.phone'),
            'location_code.required' => __('validation.required', ['attribute' => __('location.label')]),
        ];
    }
}
