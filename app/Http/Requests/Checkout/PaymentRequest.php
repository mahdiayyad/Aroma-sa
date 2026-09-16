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
            'shipping_method' => 'required|string',
            // Guests already supply an email at the address step, but an
            // authenticated phone-only (OTP-registered) account can reach
            // this step with no email anywhere in the session or on their
            // account — users.email is nullable by design. Requiring it
            // here guarantees Order.customer_email (NOT NULL) is always a
            // real address before the order is ever created.
            'email' => ['required', 'email', 'max:255'],
            'terms_accepted' => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'gateway.required' => __('validation.required', ['attribute' => __('checkout.payment_gateway')]),
            'method.required' => __('validation.required', ['attribute' => __('checkout.payment_method')]),
            'shipping_method.required' => __('validation.required', ['attribute' => __('checkout.shipping_method')]),
            'email.required' => __('validation.required', ['attribute' => __('checkout.email')]),
            'email.email' => __('validation.email', ['attribute' => __('checkout.email')]),
            'terms_accepted.accepted' => __('checkout.errors.terms_required'),
        ];
    }
}
