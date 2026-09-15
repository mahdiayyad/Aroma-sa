<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

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
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('customer_notes') && $this->input('customer_notes') !== null) {
            // Plain-text field: drop control characters (keep newlines/tabs)
            // and trim. Rendered escaped ({{ }}) at every surface — customer
            // order detail, confirmation page, confirmation email, admin — so
            // no markup is ever interpreted; this just keeps the stored value
            // clean. Not strip_tags(), which mangles legitimate input like
            // "size < 5" or a "<3".
            $notes = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $this->input('customer_notes'));
            $this->merge(['customer_notes' => trim((string) $notes)]);
        }

        foreach (['billing_address', 'shipping_address'] as $prefix) {
            $notes = $this->input("{$prefix}.additional_notes");
            if ($notes !== null) {
                $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $notes);
                $this->merge([$prefix => array_merge($this->input($prefix, []), ['additional_notes' => trim((string) $clean)])]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'billing_address.recipient_name' => 'required|string|max:100',
            // Guests must supply an email so the order confirmation can reach
            // them; authenticated users already have one on their account.
            'billing_address.email' => [auth()->check() ? 'nullable' : 'required', 'email', 'max:255'],
            'billing_address.phone' => ['required', 'string', new InternationalPhone()],
            'billing_address.method' => ['nullable', 'string', Rule::in([Address::METHOD_NATIONAL_CODE, Address::METHOD_MANUAL])],
            // The actual address (city/region/district/coordinates) is
            // resolved server-side by CheckoutController — never collected
            // as free text for the national_code method. Exactly one
            // location method is required per address: a National Address
            // code OR a map-pinned coordinate pair (see withValidator
            // below) — skipped entirely when method=manual.
            'billing_address.location_code' => ['nullable', 'string', new LocationCode()],
            'billing_address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'billing_address.longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Full Address (method=manual) fields.
            'billing_address.country' => ['required_if:billing_address.method,manual', 'nullable', 'string', 'size:2'],
            'billing_address.city' => ['required_if:billing_address.method,manual', 'nullable', 'string', 'max:100'],
            'billing_address.district' => ['required_if:billing_address.method,manual', 'nullable', 'string', 'max:100'],
            'billing_address.street_address' => ['required_if:billing_address.method,manual', 'nullable', 'string', 'max:255'],
            'billing_address.building_number' => ['required_if:billing_address.method,manual', 'nullable', 'string', 'max:20'],
            'billing_address.apartment_number' => ['nullable', 'string', 'max:20'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:10'],
            'billing_address.additional_notes' => ['nullable', 'string', 'max:500'],

            'use_shipping_for_billing' => 'boolean',

            'shipping_address.recipient_name' => 'required_if:use_shipping_for_billing,false|string|max:100',
            'shipping_address.phone' => ['required_if:use_shipping_for_billing,false', 'string', new InternationalPhone()],
            'shipping_address.method' => ['nullable', 'string', Rule::in([Address::METHOD_NATIONAL_CODE, Address::METHOD_MANUAL])],
            'shipping_address.location_code' => ['nullable', 'string', new LocationCode()],
            'shipping_address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'shipping_address.longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'shipping_address.country' => ['required_if:shipping_address.method,manual', 'nullable', 'string', 'size:2'],
            'shipping_address.city' => ['required_if:shipping_address.method,manual', 'nullable', 'string', 'max:100'],
            'shipping_address.district' => ['required_if:shipping_address.method,manual', 'nullable', 'string', 'max:100'],
            'shipping_address.street_address' => ['required_if:shipping_address.method,manual', 'nullable', 'string', 'max:255'],
            'shipping_address.building_number' => ['required_if:shipping_address.method,manual', 'nullable', 'string', 'max:20'],
            'shipping_address.apartment_number' => ['nullable', 'string', 'max:20'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:10'],
            'shipping_address.additional_notes' => ['nullable', 'string', 'max:500'],

            'customer_notes' => 'nullable|string|max:500',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->requireOneLocationMethod($validator, 'billing_address', true);

            // Match CheckoutController::storeAddress()'s own default exactly:
            // an absent use_shipping_for_billing means "same as billing", not
            // "distinct" — $this->boolean() defaults a missing field to
            // false, which is the wrong way round here.
            $sameAsBilling = $this->has('use_shipping_for_billing') ? $this->boolean('use_shipping_for_billing') : true;
            $this->requireOneLocationMethod($validator, 'shipping_address', ! $sameAsBilling);
        });
    }

    private function requireOneLocationMethod(Validator $validator, string $prefix, bool $required): void
    {
        if (! $required) {
            return;
        }

        // Manual method is fully governed by the required_if rules above —
        // this check only applies to the national_code method.
        if ($this->input("{$prefix}.method") === Address::METHOD_MANUAL) {
            return;
        }

        $hasCode = filled($this->input("{$prefix}.location_code"));
        $hasCoordinates = filled($this->input("{$prefix}.latitude")) && filled($this->input("{$prefix}.longitude"));

        if (! $hasCode && ! $hasCoordinates) {
            $validator->errors()->add("{$prefix}.location_code", __('location.errors.required_one'));
        }
    }

    public function messages(): array
    {
        $messages = [
            'billing_address.recipient_name.required' => __('validation.required', ['attribute' => __('checkout.recipient_name')]),
            'billing_address.email.required' => __('validation.required', ['attribute' => __('checkout.email')]),
            'billing_address.email.email' => __('validation.email', ['attribute' => __('checkout.email')]),
            'billing_address.phone.required' => __('validation.required', ['attribute' => __('checkout.phone')]),
        ];

        // Plain "The X field is required." for the manual-address fields —
        // Laravel's default required_if template ("...required when :other
        // is :value") would otherwise surface the raw internal field name
        // ("billing address.method") and enum value ("manual") verbatim.
        $labels = [
            'country' => __('location.manual.country'),
            'city' => __('location.manual.city'),
            'district' => __('location.manual.district'),
            'street_address' => __('location.manual.street'),
            'building_number' => __('location.manual.building_number'),
        ];
        foreach (['billing_address', 'shipping_address'] as $prefix) {
            foreach ($labels as $field => $label) {
                $messages["{$prefix}.{$field}.required_if"] = __('validation.required', ['attribute' => $label]);
            }
        }

        return $messages;
    }
}
