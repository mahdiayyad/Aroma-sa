<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Rules\LocationCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_address.recipient_name' => 'required|string|max:100',
            // Guests must supply an email so the order confirmation can reach
            // them; authenticated users already have one on their account.
            'billing_address.email' => [auth()->check() ? 'nullable' : 'required', 'email', 'max:255'],
            'billing_address.phone' => 'required|string|max:20',
            // The actual address (city/region/district/coordinates) is
            // resolved server-side by CheckoutController — never collected
            // as free text. Exactly one location method is required per
            // address: a National Address code OR a map-pinned coordinate
            // pair (see withValidator below).
            'billing_address.location_code' => ['nullable', 'string', new LocationCode()],
            'billing_address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'billing_address.longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'use_shipping_for_billing' => 'boolean',

            'shipping_address.recipient_name' => 'required_if:use_shipping_for_billing,false|string|max:100',
            'shipping_address.phone' => 'required_if:use_shipping_for_billing,false|string|max:20',
            'shipping_address.location_code' => ['nullable', 'string', new LocationCode()],
            'shipping_address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'shipping_address.longitude' => ['nullable', 'numeric', 'between:-180,180'],

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

        $hasCode = filled($this->input("{$prefix}.location_code"));
        $hasCoordinates = filled($this->input("{$prefix}.latitude")) && filled($this->input("{$prefix}.longitude"));

        if (! $hasCode && ! $hasCoordinates) {
            $validator->errors()->add("{$prefix}.location_code", __('location.errors.required_one'));
        }
    }

    public function messages(): array
    {
        return [
            'billing_address.recipient_name.required' => __('validation.required', ['attribute' => __('checkout.recipient_name')]),
            'billing_address.email.required' => __('validation.required', ['attribute' => __('checkout.email')]),
            'billing_address.email.email' => __('validation.email', ['attribute' => __('checkout.email')]),
            'billing_address.phone.required' => __('validation.required', ['attribute' => __('checkout.phone')]),
        ];
    }
}
