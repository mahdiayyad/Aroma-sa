<?php

declare(strict_types=1);

namespace App\Support\Formatting;

/**
 * Formats monetary amounts in the store currency (SAR by default). Prices are
 * stored as decimals in the database; this centralises presentation so the
 * symbol/decimals come from config/aroma.php and localise (Arabic-Indic digits
 * are left to the browser via locale — kept simple and deterministic here).
 */
class Money
{
    public static function format($amount, ?string $locale = null): string
    {
        $locale   = $locale ?: app()->getLocale();
        $decimals = (int) config('aroma.currency.decimals', 2);
        $symbol   = (string) config('aroma.currency.symbol', 'SAR');

        $value = number_format((float) $amount, $decimals);

        // In Arabic the currency symbol conventionally trails the amount.
        return $locale === 'ar'
            ? $value.' '.$symbol
            : $symbol.' '.$value;
    }
}
