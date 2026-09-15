<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\Address;
use App\Rules\InternationalPhone;
use App\Rules\LocationCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_default' => $this->boolean('is_default')]);

        // Same control-character stripping already applied to other
        // free-text fields on this form (customer_notes/gift_message
        // elsewhere) — plain text, rendered escaped everywhere, kept clean
        // at rest rather than just at display time.
        if ($this->has('additional_notes') && $this->input('additional_notes') !== null) {
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $this->input('additional_notes'));
            $this->merge(['additional_notes' => trim((string) $clean)]);
        }
    }

    public function rules(): array
    {
        return [
            'label'           => ['nullable', 'string', 'max:60'],
            'recipient_name'  => ['required', 'string', 'max:100'],
            'phone'           => ['required', 'string', new InternationalPhone()],
            'method'          => ['nullable', 'string', Rule::in([Address::METHOD_NATIONAL_CODE, Address::METHOD_MANUAL])],
            // The actual address (city/region/district/coordinates) is
            // resolved server-side by AddressController — never collected as
            // free text for the national_code method. Exactly one location
            // method is required: a National Address code OR a map-pinned
            // coordinate pair (see withValidator below) — neither is
            // individually required here so either can be omitted; this
            // branch is skipped entirely when method=manual.
            'location_code'   => ['nullable', 'string', new LocationCode()],
            'latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'       => ['nullable', 'numeric', 'between:-180,180'],

            // Full Address (method=manual) fields. 'nullable' stays alongside
            // required_if: ConvertEmptyStringsToNull turns an omitted field
            // into null, and without 'nullable' the 'string' rule would fail
            // on that null instead of being skipped when method isn't manual.
            'country'          => ['required_if:method,manual', 'nullable', 'string', 'size:2'],
            'city'             => ['required_if:method,manual', 'nullable', 'string', 'max:100'],
            'district'         => ['required_if:method,manual', 'nullable', 'string', 'max:100'],
            'street_address'   => ['required_if:method,manual', 'nullable', 'string', 'max:255'],
            'building_number'  => ['required_if:method,manual', 'nullable', 'string', 'max:20'],
            'apartment_number' => ['nullable', 'string', 'max:20'],
            'postal_code'      => ['nullable', 'string', 'max:10'],
            'additional_notes' => ['nullable', 'string', 'max:500'],

            'is_default'      => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Manual method is fully governed by the required_if rules
            // above — the code-or-coordinates check below only applies to
            // the national_code method.
            if ($this->input('method') === Address::METHOD_MANUAL) {
                return;
            }

            $hasCode = filled($this->input('location_code'));
            $hasCoordinates = filled($this->input('latitude')) && filled($this->input('longitude'));

            if (! $hasCode && ! $hasCoordinates) {
                $validator->errors()->add('location_code', __('location.errors.required_one'));
            }
        });
    }

    /**
     * Plain "The X field is required." for the manual-address fields —
     * Laravel's default required_if template ("...required when :other is
     * :value") would otherwise surface the raw internal field name
     * ("method") and enum value ("manual") verbatim.
     */
    public function messages(): array
    {
        $labels = [
            'country' => __('location.manual.country'),
            'city' => __('location.manual.city'),
            'district' => __('location.manual.district'),
            'street_address' => __('location.manual.street'),
            'building_number' => __('location.manual.building_number'),
        ];

        $messages = [];
        foreach ($labels as $field => $label) {
            $messages["{$field}.required_if"] = __('validation.required', ['attribute' => $label]);
        }

        return $messages;
    }
}
