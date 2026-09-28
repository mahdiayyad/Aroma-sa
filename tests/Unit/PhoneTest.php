<?php

namespace Tests\Unit;

use App\Rules\SaudiMobile;
use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    /** @dataProvider saudiSpellings */
    public function test_every_common_way_of_writing_a_saudi_mobile_canonicalises(string $raw): void
    {
        $this->assertSame('+966570574471', Phone::normalizeSaudi($raw));
    }

    public function saudiSpellings(): array
    {
        return [
            'canonical' => ['+966570574471'],
            'spaces' => ['+966 57 057 4471'],
            'dashes' => ['+966-57-057-4471'],
            'no plus' => ['966570574471'],
            'double zero' => ['00966570574471'],
            'national with trunk zero' => ['0570574471'],
            'national spaced' => ['057 057 4471'],
            'bare nine digits' => ['570574471'],
            'trunk zero after country code' => ['+9660570574471'],
        ];
    }

    /** @dataProvider notSaudiMobiles */
    public function test_anything_else_is_rejected(?string $raw): void
    {
        $this->assertNull(Phone::normalizeSaudi($raw));
    }

    public function notSaudiMobiles(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'letters' => ['abc'],
            'uae mobile' => ['+971501234567'],
            'saudi landline' => ['+966112345678'],
            'too short' => ['+96657057'],
            'too long' => ['+9665705744712'],
        ];
    }

    public function test_masking_never_exposes_more_than_the_last_four_digits(): void
    {
        $this->assertSame('•••••••••4471', Phone::mask('+966570574471'));
        $this->assertSame('••••', Phone::mask('123'));
        $this->assertSame('••••', Phone::mask(null));
    }

    public function test_the_saudi_mobile_rule_accepts_only_the_canonical_form(): void
    {
        $rule = new SaudiMobile();

        $this->assertTrue($rule->passes('phone', '+966570574471'));
        $this->assertFalse($rule->passes('phone', '0570574471'));
        $this->assertFalse($rule->passes('phone', '+971501234567'));
        $this->assertFalse($rule->passes('phone', ''));
    }
}
