<?php

namespace Tests\Unit;

use App\Rules\LocationCode;
use Tests\TestCase;

class LocationCodeTest extends TestCase
{
    /** @dataProvider validCodes */
    public function test_it_accepts_valid_codes(string $code): void
    {
        $this->assertTrue((new LocationCode())->passes('location_code', $code));
    }

    public function validCodes(): array
    {
        return [
            'uppercase' => ['RAHA1234'],
            'lowercase (case-insensitive)' => ['raha1234'],
            'mixed case' => ['RaHa1234'],
            'all same letter' => ['AAAA0000'],
        ];
    }

    /** @dataProvider invalidCodes */
    public function test_it_rejects_invalid_codes($code): void
    {
        $this->assertFalse((new LocationCode())->passes('location_code', $code));
    }

    public function invalidCodes(): array
    {
        return [
            'digits first' => ['1234RAHA'],
            'too short (7 chars)' => ['RAH1234'],
            'too long (9 chars)' => ['RAHAA1234'],
            'only 3 letters' => ['RAH12345'],
            'contains a space' => ['RAHA 1234'],
            'contains a special character' => ['RAHA-1234'],
            'empty string' => [''],
            'null' => [null],
            'non-string (int)' => [12345678],
        ];
    }
}
