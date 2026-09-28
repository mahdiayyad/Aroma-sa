<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Phone-number helpers for phone-only authentication. The phone is the account
 * identifier and Tawked only delivers to Saudi mobiles, so every place that
 * compares, rate-limits or stores an auth phone goes through one canonical
 * form: "+9665XXXXXXXX".
 */
final class Phone
{
    /**
     * Canonicalises any common way of writing a Saudi mobile number
     * ("+966 5X XXX XXXX", "00966…", "966…", "05…", "5…") to "+9665XXXXXXXX".
     * Returns null when the value is not a Saudi mobile number.
     */
    public static function normalizeSaudi(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (Str::startsWith($digits, '00966')) {
            $digits = substr($digits, 5);
        } elseif (Str::startsWith($digits, '966')) {
            $digits = substr($digits, 3);
        }

        // National trunk prefix ("05…", or the "+966 05…" typo).
        if (Str::startsWith($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^5\d{8}$/', $digits) === 1 ? '+966'.$digits : null;
    }

    /** "+96657•••4471"-style form for logs: never write a full number to disk. */
    public static function mask(?string $phone): string
    {
        $phone = (string) $phone;

        return strlen($phone) > 4 ? str_repeat('•', strlen($phone) - 4).substr($phone, -4) : '••••';
    }
}
