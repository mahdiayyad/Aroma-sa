<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Canonical Saudi mobile number ("+9665XXXXXXXX") — the only kind the Tawked
 * OTP provider can deliver to. Requests are normalised with
 * App\Support\Phone::normalizeSaudi() before this runs, so this is a strict
 * format check on the already-canonical value.
 */
class SaudiMobile implements Rule
{
    public function passes($attribute, $value): bool
    {
        return is_string($value) && preg_match('/^\+9665\d{8}$/', $value) === 1;
    }

    public function message(): string
    {
        return __('auth_ui.validation.phone_sa');
    }
}
