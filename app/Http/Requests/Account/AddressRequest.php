<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\InternationalPhone;
use App\Rules\LocationCode;
use Illuminate\Contracts\Validation\Validator;
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
            'phone'           => ['required', 'string', new InternationalPhone()],
            // The actual address (city/region/district/coordinates) is
            // resolved server-side by AddressController — never collected as
            // free text. Exactly one location method is required: a National
            // Address code OR a map-pinned coordinate pair (see withValidator
            // below) — neither is individually required here so either can
            // be omitted.
            'location_code'   => ['nullable', 'string', new LocationCode()],
            'latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'       => ['nullable', 'numeric', 'between:-180,180'],
            'is_default'      => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasCode = filled($this->input('location_code'));
            $hasCoordinates = filled($this->input('latitude')) && filled($this->input('longitude'));

            if (! $hasCode && ! $hasCoordinates) {
                $validator->errors()->add('location_code', __('location.errors.required_one'));
            }
        });
    }
}
