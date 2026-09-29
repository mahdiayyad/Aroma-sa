<?php

namespace Tests\Feature;

use App\Mail\EmailCodeMail;
use App\Models\EmailCode;
use App\Services\EmailCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'sara@example.com';

    protected function tearDown(): void
    {
        $this->travelBack(); // travel() freezes Carbon's "now" and would leak into the next test

        parent::tearDown();
    }

    private function service(): EmailCodeService
    {
        return app(EmailCodeService::class);
    }

    /** Sends a code and returns the plaintext the customer would read in the email. */
    private function sendAndCapture(string $email = self::EMAIL, string $purpose = EmailCode::PURPOSE_LOGIN): string
    {
        Mail::fake();
        $this->assertTrue($this->service()->send($email, $purpose));

        $code = null;
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_a_six_digit_code_is_emailed_and_only_its_hash_is_stored(): void
    {
        $code = $this->sendAndCapture('Sara@Example.com');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) {
            return $mail->hasTo(self::EMAIL) && $mail->minutes === 10;
        });

        $row = EmailCode::first();
        $this->assertSame(self::EMAIL, $row->email, 'stored in normalised form');
        $this->assertNotSame($code, $row->code_hash);
        $this->assertTrue(password_verify($code, $row->code_hash));
        $this->assertTrue($row->expires_at->isFuture());
    }

    public function test_the_right_code_verifies_once_and_only_once(): void
    {
        $code = $this->sendAndCapture();

        $this->assertSame(['valid' => true], $this->service()->verify(self::EMAIL, $code, EmailCode::PURPOSE_LOGIN));
        $this->assertSame(['valid' => false, 'error' => 'not_found'], $this->service()->verify(self::EMAIL, $code, EmailCode::PURPOSE_LOGIN));
    }

    public function test_a_wrong_code_is_counted_and_locks_after_five_tries_even_for_the_right_code(): void
    {
        $code = $this->sendAndCapture();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < EmailCodeService::MAX_ATTEMPTS; $i++) {
            $this->assertSame('invalid_code', $this->service()->verify(self::EMAIL, $wrong, EmailCode::PURPOSE_LOGIN)['error']);
        }

        $this->assertSame('too_many_attempts', $this->service()->verify(self::EMAIL, $wrong, EmailCode::PURPOSE_LOGIN)['error']);
        $this->assertSame('too_many_attempts', $this->service()->verify(self::EMAIL, $code, EmailCode::PURPOSE_LOGIN)['error']);
    }

    public function test_a_code_expires_after_ten_minutes(): void
    {
        $code = $this->sendAndCapture();

        $this->travel(11)->minutes();

        $this->assertSame('expired', $this->service()->verify(self::EMAIL, $code, EmailCode::PURPOSE_LOGIN)['error']);
    }

    public function test_asking_for_a_new_code_supersedes_the_old_one(): void
    {
        $first = $this->sendAndCapture();
        $second = $this->sendAndCapture();
        $this->assertNotSame($first, $second);

        $this->assertSame('invalid_code', $this->service()->verify(self::EMAIL, $first, EmailCode::PURPOSE_LOGIN)['error']);
        $this->assertSame(['valid' => true], $this->service()->verify(self::EMAIL, $second, EmailCode::PURPOSE_LOGIN));
    }

    public function test_a_code_is_only_good_for_the_purpose_it_was_issued_for(): void
    {
        $code = $this->sendAndCapture(self::EMAIL, EmailCode::PURPOSE_LOGIN);

        $this->assertSame('not_found', $this->service()->verify(self::EMAIL, $code, EmailCode::PURPOSE_ADD_CREDENTIALS)['error']);
    }

    public function test_a_code_is_only_good_for_the_address_it_was_sent_to(): void
    {
        $code = $this->sendAndCapture();

        $this->assertSame('not_found', $this->service()->verify('other@example.com', $code, EmailCode::PURPOSE_LOGIN)['error']);
    }

    public function test_a_mail_failure_reports_false_and_keeps_nothing(): void
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('smtp down'));

        $this->assertFalse($this->service()->send(self::EMAIL, EmailCode::PURPOSE_LOGIN));
        $this->assertDatabaseCount('email_codes', 0);
    }
}
