<?php

declare(strict_types=1);

namespace App\Support\ProductSheet;

/**
 * Turns raw spreadsheet cells into clean PHP values. Forgiving where a person
 * would reasonably expect it (Arabic-Indic digits, "1,200.50", "yes"/"نعم",
 * a stray "SAR"), strict where guessing would be dangerous.
 */
final class RowNormalizer
{
    private const TRUE_WORDS = ['1', 'yes', 'y', 'true', 'on', 'نعم', 'ايوه', 'أيوه'];
    private const FALSE_WORDS = ['0', 'no', 'n', 'false', 'off', 'لا', 'كلا'];
    private const ACTIVE_WORDS = ['active', 'enabled', 'published', 'visible', 'نشط', 'فعال', 'مفعل', '1', 'yes', 'true', 'نعم'];
    private const INACTIVE_WORDS = ['inactive', 'disabled', 'draft', 'hidden', 'unpublished', 'غير نشط', 'غير فعال', 'معطل', 'مخفي', '0', 'no', 'false', 'لا'];

    /** A raw Excel cell as a trimmed string, or null when empty. */
    public static function cell($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_float($value)) {
            // 100.0 from a numeric cell must read as "100" (SKUs, integers), not "100.0".
            $value = floor($value) === $value && abs($value) < 1e15 ? (string) (int) $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        // Non-breaking / zero-width characters copied from web pages and chat apps.
        $value = trim(preg_replace('/[\x{00A0}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', ' ', $value) ?? $value);

        return $value === '' ? null : $value;
    }

    public static function isClear(?string $value): bool
    {
        return $value !== null && mb_strtolower($value, 'UTF-8') === Columns::CLEAR_TOKEN;
    }

    /** ٣٥٠ / ۳۵۰ -> 350, Arabic decimal/thousands marks -> ASCII. */
    public static function asciiDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => ',',
        ]);
    }

    /** @throws CellException */
    public static function money(string $value): float
    {
        $clean = self::asciiDigits($value);
        $clean = preg_replace('/(sar|s\.?r\.?|ر\.?\s?س\.?|ريال|﷼)/iu', '', $clean) ?? $clean;
        $clean = str_replace([' ', "\u{00A0}"], '', $clean);

        $hasComma = strpos($clean, ',') !== false;
        $hasDot = strpos($clean, '.') !== false;

        if ($hasComma && $hasDot) {
            // The separator that comes last is the decimal mark: 1,200.50 vs 1.200,50.
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasComma) {
            // "1,200" = thousands; "12,5" / "12,50" = decimal.
            $clean = preg_match('/^\d{1,3}(,\d{3})+$/', $clean) ? str_replace(',', '', $clean) : str_replace(',', '.', $clean);
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $clean)) {
            throw new CellException('not_a_number', ['value' => $value]);
        }

        return round((float) $clean, 2);
    }

    /** @throws CellException */
    public static function integer(string $value): int
    {
        $clean = self::asciiDigits($value);
        $clean = str_replace([',', ' '], '', $clean);

        if (! preg_match('/^\d+(\.0+)?$/', $clean)) {
            throw new CellException('not_an_integer', ['value' => $value]);
        }

        return (int) $clean;
    }

    /** @throws CellException */
    public static function boolean(string $value): bool
    {
        $word = mb_strtolower(trim($value), 'UTF-8');

        if (in_array($word, self::TRUE_WORDS, true)) {
            return true;
        }

        if (in_array($word, self::FALSE_WORDS, true)) {
            return false;
        }

        throw new CellException('not_yes_no', ['value' => $value]);
    }

    /** "active"/"inactive" (and their Arabic/common equivalents) -> is_active. @throws CellException */
    public static function status(string $value): bool
    {
        $word = mb_strtolower(trim($value), 'UTF-8');

        if (in_array($word, self::ACTIVE_WORDS, true)) {
            return true;
        }

        if (in_array($word, self::INACTIVE_WORDS, true)) {
            return false;
        }

        throw new CellException('not_a_status', ['value' => $value]);
    }

    /** Split a multi-value cell on | or new lines; trims, drops blanks and duplicates. @return array<int,string> */
    public static function list(string $value): array
    {
        $parts = preg_split('/[|\r\n]+/', $value) ?: [];
        $parts = array_map('trim', $parts);

        return array_values(array_unique(array_filter($parts, function ($p) {
            return $p !== '';
        })));
    }

    /**
     * Arabic-aware key for matching category/brand names typed by hand:
     * case, spacing, tashkeel and alef/yeh variants don't matter.
     */
    public static function matchKey(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $value) ?? $value;
        $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
