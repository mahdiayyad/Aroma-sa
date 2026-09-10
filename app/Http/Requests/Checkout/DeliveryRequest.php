<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('delivery_instructions') && $this->input('delivery_instructions') !== null) {
            // Plain-text field: drop control characters (keep newlines) and
            // trim. Rendered escaped ({{ }}) on the admin order screen.
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $this->input('delivery_instructions'));
            $this->merge(['delivery_instructions' => trim((string) $clean)]);
        }
    }

    public function rules(): array
    {
        $minDate = now()->addDays((int) config('aroma.delivery.min_lead_days', 1))->toDateString();
        $maxDate = now()->addDays((int) config('aroma.delivery.max_lead_days', 30))->toDateString();

        return [
            'delivery_date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$minDate}", "before_or_equal:{$maxDate}"],
            'delivery_time_slot' => ['required', Rule::in(Order::DELIVERY_SLOTS)],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        $minDate = now()->addDays((int) config('aroma.delivery.min_lead_days', 1))->toDateString();
        $maxDate = now()->addDays((int) config('aroma.delivery.max_lead_days', 30))->toDateString();

        return [
            'delivery_date.required' => __('delivery.errors.date_required'),
            'delivery_date.after_or_equal' => __('delivery.errors.date_range', ['min' => $minDate, 'max' => $maxDate]),
            'delivery_date.before_or_equal' => __('delivery.errors.date_range', ['min' => $minDate, 'max' => $maxDate]),
            'delivery_time_slot.required' => __('delivery.errors.slot_required'),
        ];
    }
}
