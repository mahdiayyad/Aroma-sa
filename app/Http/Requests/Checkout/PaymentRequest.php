<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway' => 'required|in:moyasar,tabby,tamara',
            'method' => 'required|string',
            'coupon_code' => 'nullable|string|max:50',
            'shipping_method' => 'required|string',
            'terms_accepted' => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'gateway.required' => __('validation.required', ['attribute' => __('checkout.payment_gateway')]),
            'method.required' => __('validation.required', ['attribute' => __('checkout.payment_method')]),
            'shipping_method.required' => __('validation.required', ['attribute' => __('checkout.shipping_method')]),
            'terms_accepted.accepted' => __('checkout.errors.terms_required'),
        ];
    }
}
