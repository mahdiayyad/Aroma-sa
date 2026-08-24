<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Saudi National Address short code: 4 letters followed by 4 digits
 * (e.g. RAHA1234). Case-insensitive here — LocationLookupService is the
 * single place that normalises to uppercase before lookup/storage, so
 * every FormRequest that uses this rule doesn't need to repeat that logic.
 */
class LocationCode implements Rule
{
    public function passes($attribute, $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z]{4}\d{4}$/', trim($value)) === 1;
    }

    public function message(): string
    {
        return __('location.errors.invalid_format');
    }
}
