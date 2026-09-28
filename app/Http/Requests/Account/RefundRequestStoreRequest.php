<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RefundRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')->where('user_id', $this->user()->id)],
            'reason' => ['required', 'string', Rule::in(['damaged', 'wrong_item', 'not_as_described', 'no_longer_needed', 'other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('order_id')) {
                return;
            }

            $order = Order::find($this->input('order_id'));

            if ($order && ! $order->isPaid()) {
                $validator->errors()->add('order_id', __('refund.errors.order_not_paid'));
            }
        });
    }
}
