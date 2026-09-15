<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Models\Address;
use App\Rules\InternationalPhone;
use App\Rules\LocationCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GiftOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_gift'      => $this->boolean('is_gift'),
            'is_anonymous' => $this->boolean('is_anonymous'),
            'gift_wrap'    => $this->boolean('gift_wrap'),
        ]);

        // Plain-text fields: drop control characters (keep newlines) and trim.
        // Rendered escaped ({{ }}) everywhere including the confirmation email,
        // so no markup is interpreted — not strip_tags(), which would mangle
        // legitimate input like "<3".
        foreach (['gift_message', 'gift_to', 'gift_from'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $this->input($field));
                $this->merge([$field => trim((string) $clean)]);
            }
        }

        $notes = $this->input('recipient.additional_notes');
        if ($notes !== null) {
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $notes);
            $this->merge(['recipient' => array_merge($this->input('recipient', []), ['additional_notes' => trim((string) $clean)])]);
        }
    }

    public function rules(): array
    {
        $isGift = $this->boolean('is_gift');
        $maxChars = (int) config('aroma.gifting.message_max_chars', 200);
        $maxLines = (int) config('aroma.gifting.message_max_lines', 5);

        return [
            'is_gift' => ['boolean'],

            // When it's a gift, the recipient's own shipping address is
            // collected here — it replaces checkout.shipping_address (see the
            // "Recipient model" decision: shipping_address IS the recipient).
            // 'nullable' matters here even though requiredIf already governs
            // whether the field is mandatory: when it's not a gift, the empty
            // input becomes null (ConvertEmptyStringsToNull), and without
            // 'nullable' the 'string' rule below fails on that null instead of
            // being skipped — which is exactly the bug this fixes.
            'recipient.recipient_name'  => [Rule::requiredIf($isGift), 'nullable', 'string', 'max:100'],
            'recipient.phone'           => [Rule::requiredIf($isGift), 'nullable', 'string', new InternationalPhone()],
            'recipient.method'          => ['nullable', 'string', Rule::in([Address::METHOD_NATIONAL_CODE, Address::METHOD_MANUAL])],
            // Resolved server-side via LocationLookupService (code) or stored
            // directly (map pin) — not collected as free text for the
            // national_code method. Exactly one of location_code /
            // (latitude+longitude) is required when it's a gift (see
            // withValidator below) and method isn't manual; neither is
            // individually required here so either can be omitted.
            'recipient.location_code'   => ['nullable', 'string', new LocationCode()],
            'recipient.latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'recipient.longitude'       => ['nullable', 'numeric', 'between:-180,180'],

            // Full Address (method=manual) fields.
            'recipient.country'          => ['required_if:recipient.method,manual', 'nullable', 'string', 'size:2'],
            'recipient.city'             => ['required_if:recipient.method,manual', 'nullable', 'string', 'max:100'],
            'recipient.district'         => ['required_if:recipient.method,manual', 'nullable', 'string', 'max:100'],
            'recipient.street_address'   => ['required_if:recipient.method,manual', 'nullable', 'string', 'max:255'],
            'recipient.building_number'  => ['required_if:recipient.method,manual', 'nullable', 'string', 'max:20'],
            'recipient.apartment_number' => ['nullable', 'string', 'max:20'],
            'recipient.postal_code'      => ['nullable', 'string', 'max:10'],
            'recipient.additional_notes' => ['nullable', 'string', 'max:500'],

            'is_anonymous'     => ['boolean'],
            'gift_wrap'        => ['boolean'],
            'greeting_card_id' => ['nullable', 'integer', 'exists:gift_cards,id'],
            'gift_to'          => ['nullable', 'string', 'max:100'],
            'gift_from'        => ['nullable', 'string', 'max:100'],
            'gift_message'     => [
                'nullable', 'string', "max:{$maxChars}",
                function ($attribute, $value, $fail) use ($maxLines) {
                    if ($value && substr_count((string) $value, "\n") >= $maxLines) {
                        $fail(__('gift.errors.too_many_lines', ['max' => $maxLines]));
                    }
                },
            ],
            'gift_media_url'     => ['nullable', 'url', 'max:500'],
            'gift_signature_data' => ['nullable', 'string', 'starts_with:data:image/png;base64,'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('is_gift')) {
                return;
            }

            // Manual method is fully governed by the required_if rules
            // above — this check only applies to the national_code method.
            if ($this->input('recipient.method') === Address::METHOD_MANUAL) {
                return;
            }

            $hasCode = filled($this->input('recipient.location_code'));
            $hasCoordinates = filled($this->input('recipient.latitude')) && filled($this->input('recipient.longitude'));

            if (! $hasCode && ! $hasCoordinates) {
                $validator->errors()->add('recipient.location_code', __('location.errors.required_one'));
            }
        });
    }

    /**
     * Plain "The X field is required." for the manual-address fields —
     * Laravel's default required_if template ("...required when :other is
     * :value") would otherwise surface the raw internal field name
     * ("recipient.method") and enum value ("manual") verbatim.
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
            $messages["recipient.{$field}.required_if"] = __('validation.required', ['attribute' => $label]);
        }

        return $messages;
    }
}
