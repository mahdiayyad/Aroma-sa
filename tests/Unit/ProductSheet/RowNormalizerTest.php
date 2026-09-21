<?php

namespace Tests\Unit\ProductSheet;

use App\Support\ProductSheet\CellException;
use App\Support\ProductSheet\RowNormalizer;
use PHPUnit\Framework\TestCase;

class RowNormalizerTest extends TestCase
{
    /** @dataProvider cells */
    public function test_cell_reads_raw_excel_values_as_trimmed_strings_or_null($raw, ?string $expected): void
    {
        $this->assertSame($expected, RowNormalizer::cell($raw));
    }

    public function cells(): array
    {
        return [
            'null'                       => [null, null],
            'blank'                      => ['   ', null],
            'trimmed'                    => ['  Oud  ', 'Oud'],
            'whole float is an integer'  => [100.0, '100'],
            'fractional float'           => [12.5, '12.5'],
            'int'                        => [7, '7'],
            'true'                       => [true, 'true'],
            'false'                      => [false, 'false'],
            'non-breaking space'         => ["\u{00A0}SKU-1\u{00A0}", 'SKU-1'],
            'zero-width + bidi marks'    => ["\u{200F}عود\u{200B}", 'عود'],
            'date'                       => [new \DateTimeImmutable('2026-09-21 10:00'), '2026-09-21'],
        ];
    }

    /** @dataProvider money */
    public function test_money_understands_the_ways_people_type_prices(string $input, float $expected): void
    {
        $this->assertSame($expected, RowNormalizer::money($input));
    }

    public function money(): array
    {
        return [
            'plain'                    => ['350', 350.0],
            'decimals'                 => ['99.90', 99.9],
            'us thousands'             => ['1,200.50', 1200.5],
            'eu thousands'             => ['1.200,50', 1200.5],
            'thousands only'           => ['1,200', 1200.0],
            'decimal comma'            => ['12,5', 12.5],
            'arabic-indic digits'      => ['٣٥٠', 350.0],
            'arabic decimal mark'      => ['١٢٫٥٠', 12.5],
            'currency word'            => ['350 SAR', 350.0],
            'arabic currency'          => ['350 ريال', 350.0],
            'riyal sign'               => ['﷼ 350', 350.0],
            'rounded to halalas'       => ['10.999', 11.0],
        ];
    }

    /** @dataProvider badNumbers */
    public function test_money_rejects_what_is_not_a_price(string $input): void
    {
        try {
            RowNormalizer::money($input);
            $this->fail("'{$input}' should not parse");
        } catch (CellException $e) {
            $this->assertSame('not_a_number', $e->errorKey);
            $this->assertSame($input, $e->replace['value']);
        }
    }

    public function badNumbers(): array
    {
        return [['free'], ['12abc'], ['-5'], ['1.2.3'], ['']];
    }

    public function test_integer_accepts_whole_numbers_only(): void
    {
        $this->assertSame(12, RowNormalizer::integer('12'));
        $this->assertSame(12, RowNormalizer::integer('12.0'));
        $this->assertSame(1200, RowNormalizer::integer('1,200'));
        $this->assertSame(35, RowNormalizer::integer('٣٥'));

        foreach (['1.5', '-3', 'ten', ''] as $bad) {
            try {
                RowNormalizer::integer($bad);
                $this->fail("'{$bad}' should not parse");
            } catch (CellException $e) {
                $this->assertSame('not_an_integer', $e->errorKey);
            }
        }
    }

    public function test_boolean_and_status_accept_english_and_arabic_words(): void
    {
        foreach (['yes', 'YES', 'Y', 'true', '1', 'on', 'نعم'] as $word) {
            $this->assertTrue(RowNormalizer::boolean($word), $word);
        }
        foreach (['no', 'N', 'false', '0', 'off', 'لا'] as $word) {
            $this->assertFalse(RowNormalizer::boolean($word), $word);
        }
        foreach (['active', 'Active', 'published', 'نشط', 'فعال', '1'] as $word) {
            $this->assertTrue(RowNormalizer::status($word), $word);
        }
        foreach (['inactive', 'Draft', 'hidden', 'غير نشط', 'معطل', '0'] as $word) {
            $this->assertFalse(RowNormalizer::status($word), $word);
        }

        $this->expectException(CellException::class);
        RowNormalizer::boolean('maybe');
    }

    public function test_an_unknown_status_names_its_error_key(): void
    {
        try {
            RowNormalizer::status('pending');
            $this->fail('should not parse');
        } catch (CellException $e) {
            $this->assertSame('not_a_status', $e->errorKey);
        }
    }

    public function test_list_splits_on_pipes_and_newlines_and_drops_blanks_and_duplicates(): void
    {
        $this->assertSame(['a.jpg', 'b.jpg', 'c.jpg'], RowNormalizer::list("a.jpg | b.jpg\nc.jpg||a.jpg\r\n"));
        $this->assertSame([], RowNormalizer::list(' | '));
    }

    public function test_the_clear_token_is_case_insensitive_and_exact(): void
    {
        $this->assertTrue(RowNormalizer::isClear('[clear]'));
        $this->assertTrue(RowNormalizer::isClear('[CLEAR]'));
        $this->assertFalse(RowNormalizer::isClear('clear'));
        $this->assertFalse(RowNormalizer::isClear('[clear] please'));
        $this->assertFalse(RowNormalizer::isClear(null));
    }

    public function test_match_key_ignores_case_spacing_tashkeel_and_alef_yeh_variants(): void
    {
        $this->assertSame(RowNormalizer::matchKey('  Perfumes '), RowNormalizer::matchKey('perfumes'));
        $this->assertSame(RowNormalizer::matchKey('Home   Fragrance'), RowNormalizer::matchKey('home fragrance'));
        $this->assertSame(RowNormalizer::matchKey('أزياء'), RowNormalizer::matchKey('ازياء'));
        $this->assertSame(RowNormalizer::matchKey('عَبَايَات'), RowNormalizer::matchKey('عبايات'));
        $this->assertSame(RowNormalizer::matchKey('مصلى'), RowNormalizer::matchKey('مصلي'));
        $this->assertNotSame(RowNormalizer::matchKey('العطور'), RowNormalizer::matchKey('العبايات'));
    }
}
