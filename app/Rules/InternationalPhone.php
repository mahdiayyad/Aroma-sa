<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * E.164 international phone number: a leading "+", then 7-15 digits total
 * with no leading zero after the "+" (ITU E.164). This is the exact format
 * intl-tel-input's getNumber() always submits (see public/js/intl-phone.js)
 * — per-country length/validity is enforced client-side by that library
 * (backed by libphonenumber's data) before the form ever posts; this is a
 * lightweight server-side sanity check, not a full re-validation.
 */
class InternationalPhone implements Rule
{
    public function passes($attribute, $value): bool
    {
        return is_string($value) && preg_match('/^\+[1-9]\d{6,14}$/', trim($value)) === 1;
    }

    public function message(): string
    {
        return __('auth_ui.validation.phone');
    }
}
