<?php

namespace Tests\Unit;

use App\Support\Formatting\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_formats_with_the_symbol_leading_in_english(): void
    {
        $this->assertSame('ر.س 100.50', Money::format(100.5, 'en'));
    }

    public function test_it_formats_with_the_symbol_trailing_in_arabic(): void
    {
        $this->assertSame('100.50 ر.س', Money::format(100.5, 'ar'));
    }

    public function test_it_respects_configured_decimals(): void
    {
        $this->assertSame('ر.س 12.00', Money::format(12, 'en'));
    }
}
