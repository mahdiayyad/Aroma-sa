<?php

namespace Tests\Unit;

use App\Support\Email;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function test_it_lowercases_and_trims(): void
    {
        $this->assertSame('sara@example.com', Email::normalize("  Sara@Example.COM \n"));
        $this->assertSame('', Email::normalize(null));
    }

    public function test_masking_keeps_the_first_letter_and_the_domain_only(): void
    {
        $this->assertSame('s***@example.com', Email::mask('sara@example.com'));
        $this->assertSame('m***@mail.co.sa', Email::mask('mahdi.ayyad@mail.co.sa'));
        $this->assertSame('•••', Email::mask('not-an-email'));
        $this->assertSame('•••', Email::mask(null));
    }
}
