<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

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
            'billing_address.phone' => 'required|string|max:20',
            'billing_address.street_address' => 'required|string|max:255',
            'billing_address.city' => 'required|string|max:100',
            'billing_address.region' => 'required|string|max:100',
            'billing_address.postal_code' => 'nullable|string|max:20',

            'use_shipping_for_billing' => 'boolean',

            'shipping_address.recipient_name' => 'required_if:use_shipping_for_billing,false|string|max:100',
            'shipping_address.phone' => 'required_if:use_shipping_for_billing,false|string|max:20',
            'shipping_address.street_address' => 'required_if:use_shipping_for_billing,false|string|max:255',
            'shipping_address.city' => 'required_if:use_shipping_for_billing,false|string|max:100',
            'shipping_address.region' => 'required_if:use_shipping_for_billing,false|string|max:100',
            'shipping_address.postal_code' => 'nullable|string|max:20',

            'customer_notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'billing_address.recipient_name.required' => __('validation.required', ['attribute' => __('checkout.recipient_name')]),
            'billing_address.phone.required' => __('validation.required', ['attribute' => __('checkout.phone')]),
            'billing_address.street_address.required' => __('validation.required', ['attribute' => __('checkout.street_address')]),
            'billing_address.city.required' => __('validation.required', ['attribute' => __('checkout.city')]),
            'billing_address.region.required' => __('validation.required', ['attribute' => __('checkout.region')]),
        ];
    }
}
