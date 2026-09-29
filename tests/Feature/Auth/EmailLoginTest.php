<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailCodeMail;
use App\Models\EmailCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Second sign-in option: email + password, then a code emailed to that address.
 * Mail is faked; the code is read off the captured mailable.
 */
class EmailLoginTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'noura@example.com';
    private const PASSWORD = 'Passw0rd1';

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function account(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => null,
        ], $overrides));
    }

    private function step1(string $email = self::EMAIL, string $password = self::PASSWORD)
    {
        return $this->postJson(route('login.email'), ['email' => $email, 'password' => $password]);
    }

    private function step2(string $code)
    {
        return $this->postJson(route('login.email.verify'), ['code' => $code]);
    }

    /** Runs step 1 and returns the code that was emailed. */
    private function requestCode(string $email = self::EMAIL): string
    {
        Mail::fake();
        $this->step1($email)->assertOk();

        $code = null;
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function wrong(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    /* ---- The page ------------------------------------------------------------- */

    public function test_the_sign_in_page_offers_both_options(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('email_auth.tab_phone'))
            ->assertSee(__('email_auth.tab_email'))
            ->assertSee('id="otpLoginPanel"', false)
            ->assertSee('id="emailLoginPanel"', false)
            ->assertSee(route('login.email'), false)
            ->assertSee(route('login.email.verify'), false);
    }

    public function test_the_email_tab_can_be_opened_directly(): void
    {
        $html = $this->get(route('login', ['method' => 'email']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="authMethodEmail"[^>]*checked/', $html);
    }

    /* ---- Step 1: password, then a code is emailed ----------------------------- */

    public function test_correct_credentials_email_a_code_but_do_not_sign_anyone_in(): void
    {
        $this->account();
        Mail::fake();

        $this->step1('  Noura@Example.com ')
            ->assertOk()
            ->assertJson(['display' => 'n***@example.com'])
            ->assertJsonStructure(['message', 'display', 'expires_in']);

        $this->assertGuest();
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) {
            return $mail->hasTo(self::EMAIL) && preg_match('/^\d{6}$/', $mail->code) === 1;
        });
        $row = EmailCode::first();
        $this->assertSame(EmailCode::PURPOSE_LOGIN, $row->purpose);
        $this->assertNotSame(1, preg_match('/^\d{6}$/', $row->code_hash), 'only a hash is stored');
    }

    public function test_every_credential_failure_looks_identical_and_sends_nothing(): void
    {
        $this->account();
        $this->account(['email' => 'phoneonly@example.com', 'password' => null]);
        Mail::fake();

        $wrongPassword = $this->step1(self::EMAIL, 'WrongPass1');
        $unknownEmail = $this->step1('nobody@example.com');
        $noPassword = $this->step1('phoneonly@example.com', 'anything1');

        foreach ([$wrongPassword, $unknownEmail, $noPassword] as $response) {
            $response->assertStatus(422)->assertJson(['message' => __('email_auth.errors.invalid_credentials')]);
        }
        $this->assertSame($wrongPassword->getContent(), $unknownEmail->getContent());
        $this->assertSame($wrongPassword->getContent(), $noPassword->getContent());

        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_codes', 0);
        $this->assertGuest();
    }

    public function test_a_disabled_account_is_refused_after_a_correct_password_without_emailing_a_code(): void
    {
        $this->account(['is_active' => false]);
        Mail::fake();

        $this->step1()->assertStatus(403)->assertJson(['message' => __('otp.errors.inactive')]);

        Mail::assertNothingSent();
    }

    public function test_an_email_failure_is_reported_and_nothing_is_left_behind(): void
    {
        $this->account();
        Mail::shouldReceive('to')->andThrow(new \Exception('smtp down'));

        $this->step1()->assertStatus(503)->assertJson(['message' => __('email_auth.errors.send_failed')]);

        $this->assertDatabaseCount('email_codes', 0);
        $this->assertGuest();
    }

    public function test_step_one_validates_its_input(): void
    {
        Mail::fake();

        $this->postJson(route('login.email'), ['email' => 'not-an-email', 'password' => 'x'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson(route('login.email'), ['email' => self::EMAIL])->assertStatus(422)->assertJsonValidationErrors('password');

        Mail::assertNothingSent();
    }

    /* ---- Step 2: the code signs you in ---------------------------------------- */

    public function test_the_emailed_code_signs_the_customer_in_and_remembers_them(): void
    {
        $user = $this->account();
        $code = $this->requestCode();

        $response = $this->step2($code)->assertOk()->assertJson(['redirect' => route('account.dashboard')]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue(
            collect($response->baseResponse->headers->getCookies())->contains(function ($cookie) {
                return strpos($cookie->getName(), 'remember_web') === 0;
            }),
            'a remember-me cookie is issued'
        );
    }

    public function test_the_customer_lands_where_they_were_headed(): void
    {
        $this->account();
        $code = $this->requestCode();

        $this->withSession(['url.intended' => route('checkout.address')])
            ->postJson(route('login.email.verify'), ['code' => $code])
            ->assertOk()
            ->assertJson(['redirect' => route('checkout.address')]);
    }

    public function test_a_code_alone_does_nothing_without_the_password_step(): void
    {
        $this->account();
        Mail::fake();
        $this->step1()->assertOk(); // a code now exists for this address…

        $code = null;
        Mail::assertSent(EmailCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        // …but a different browser session never passed the password step.
        $this->flushSession();
        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.errors.session_expired')]);
        $this->assertGuest();
    }

    public function test_a_wrong_code_is_rejected_and_the_right_one_still_works(): void
    {
        $this->account();
        $code = $this->requestCode();

        $this->step2($this->wrong($code))->assertStatus(422)->assertJson(['message' => __('otp.errors.invalid_code')]);
        $this->assertGuest();

        $this->step2($code)->assertOk();
    }

    public function test_five_wrong_codes_burn_the_code_and_the_password_step_must_be_repeated(): void
    {
        $this->account();
        $code = $this->requestCode();

        for ($i = 0; $i < 5; $i++) {
            $this->step2($this->wrong($code))->assertStatus(422);
        }
        $this->step2($this->wrong($code))->assertStatus(422)->assertJson(['message' => __('otp.errors.too_many_attempts')]);

        // Even the right code is useless now, and the session marker is gone.
        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.errors.session_expired')]);
        $this->assertGuest();
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $this->account();
        $code = $this->requestCode();

        $this->travel(11)->minutes();

        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('otp.errors.expired')]);
    }

    public function test_the_half_finished_sign_in_expires_after_fifteen_minutes(): void
    {
        $this->account();
        $code = $this->requestCode();

        $this->travel(16)->minutes();

        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.errors.session_expired')]);
        $this->assertGuest();
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $this->account();
        $code = $this->requestCode();

        $this->step2($code)->assertOk();
        auth()->logout();

        $this->step2($code)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_asking_again_supersedes_the_earlier_code(): void
    {
        $this->account();
        $first = $this->requestCode();
        $second = $this->requestCode();

        $this->step2($first)->assertStatus(422);
        $this->step2($second)->assertOk();
    }

    public function test_an_account_disabled_between_the_two_steps_cannot_finish_signing_in(): void
    {
        $user = $this->account();
        $code = $this->requestCode();

        $user->update(['is_active' => false]);

        $this->step2($code)->assertStatus(403);
        $this->assertGuest();
    }

    public function test_an_email_changed_between_the_two_steps_cannot_finish_signing_in(): void
    {
        $user = $this->account();
        $code = $this->requestCode();

        $user->update(['email' => 'changed@example.com']);

        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.errors.session_expired')]);
        $this->assertGuest();
    }

    public function test_step_two_validates_the_code_format(): void
    {
        $this->account();
        $this->requestCode();

        $this->step2('12ab56')->assertStatus(422)->assertJsonValidationErrors('code');
        $this->step2('12345')->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /* ---- Throttling ----------------------------------------------------------- */

    public function test_guessing_passwords_for_one_email_is_throttled(): void
    {
        $this->account();
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->step1(self::EMAIL, 'WrongPass'.$i)->assertStatus(422);
        }

        // The sixth attempt is refused even with the RIGHT password.
        $this->step1()->assertStatus(429)->assertJson(['message' => __('otp.errors.rate_limited')]);
        Mail::assertNothingSent();
    }

    public function test_hammering_the_code_endpoint_is_throttled(): void
    {
        // No password step passed, so every attempt is a blind guess.
        for ($i = 0; $i < 10; $i++) {
            $this->step2('123456')->assertStatus(422);
        }

        $this->step2('123456')->assertStatus(429)->assertJson(['message' => __('otp.errors.rate_limited')]);
    }

    /* ---- Doesn't disturb the phone option ------------------------------------- */

    public function test_the_password_login_url_still_does_not_exist(): void
    {
        $this->post('/login', ['login' => self::EMAIL, 'password' => self::PASSWORD])->assertStatus(405);
    }
}
