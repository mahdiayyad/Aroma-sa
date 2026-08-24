<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

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
            // Resolved server-side via LocationLookupService (code) or stored
            // directly (map pin) — not collected as free text. Exactly one of
            // location_code / (latitude+longitude) is required when it's a
            // gift (see withValidator below); neither is individually
            // required here so either can be omitted.
            'recipient.location_code'   => ['nullable', 'string', new LocationCode()],
            'recipient.latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'recipient.longitude'       => ['nullable', 'numeric', 'between:-180,180'],

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

            $hasCode = filled($this->input('recipient.location_code'));
            $hasCoordinates = filled($this->input('recipient.latitude')) && filled($this->input('recipient.longitude'));

            if (! $hasCode && ! $hasCoordinates) {
                $validator->errors()->add('recipient.location_code', __('location.errors.required_one'));
            }
        });
    }
}
