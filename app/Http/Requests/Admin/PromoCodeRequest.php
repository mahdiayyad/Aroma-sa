<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\PromoCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by auth + admin middleware
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'is_active' => $this->boolean('is_active'),
            'free_shipping' => $this->boolean('free_shipping'),
            'first_order_only' => $this->boolean('first_order_only'),
            'customer_restricted' => $this->boolean('customer_restricted'),
        ];

        if ($this->has('code') && $this->input('code') !== null) {
            $merge['code'] = strtoupper(trim((string) $this->input('code')));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $promoCode = $this->route('promo_code');
        $ignoreId = $promoCode ? $promoCode->id : null;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],

            'discount_type' => ['required', Rule::in(PromoCode::TYPES)],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'free_shipping' => ['boolean'],

            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],

            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],

            'first_order_only' => ['boolean'],
            'customer_restricted' => ['boolean'],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['integer', 'exists:users,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('discount_type') === PromoCode::TYPE_PERCENTAGE && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('admin.promo_codes.errors.percentage_over_100'));
            }
        });
    }
}
