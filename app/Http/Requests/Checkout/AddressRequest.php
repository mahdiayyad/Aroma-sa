<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Rules\LocationCode;
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
            // The actual address (street/city/region/coordinates) is resolved
            // server-side from this code by CheckoutController via
            // LocationLookupService — not collected as free text anymore.
            'billing_address.location_code' => ['required', 'string', new LocationCode()],

            'use_shipping_for_billing' => 'boolean',

            'shipping_address.recipient_name' => 'required_if:use_shipping_for_billing,false|string|max:100',
            'shipping_address.phone' => 'required_if:use_shipping_for_billing,false|string|max:20',
            'shipping_address.location_code' => ['required_if:use_shipping_for_billing,false', 'nullable', 'string', new LocationCode()],

            'customer_notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'billing_address.recipient_name.required' => __('validation.required', ['attribute' => __('checkout.recipient_name')]),
            'billing_address.email.required' => __('validation.required', ['attribute' => __('checkout.email')]),
            'billing_address.email.email' => __('validation.email', ['attribute' => __('checkout.email')]),
            'billing_address.phone.required' => __('validation.required', ['attribute' => __('checkout.phone')]),
            'billing_address.location_code.required' => __('validation.required', ['attribute' => __('location.label')]),
            'shipping_address.location_code.required_if' => __('validation.required', ['attribute' => __('location.label')]),
        ];
    }
}
