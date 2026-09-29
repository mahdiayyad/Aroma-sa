<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailCodeMail;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sign-up's second door: create an account with email + password, confirmed by
 * a code emailed to that address. Mail is faked; the code is read off the
 * captured mailable, same convention as EmailLoginTest.
 */
class EmailRegisterTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Layla Ahmed';
    private const EMAIL = 'layla@example.com';
    private const PASSWORD = 'Passw0rd1';

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides);
    }

    private function step1(array $overrides = [])
    {
        return $this->postJson(route('register.email'), $this->payload($overrides));
    }

    private function step2(string $code)
    {
        return $this->postJson(route('register.email.verify'), ['code' => $code]);
    }

    /** Runs step 1 and returns the code that was emailed. */
    private function requestCode(array $overrides = []): string
    {
        Mail::fake();
        $this->step1($overrides)->assertOk();

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

    /* ---- The page --------------------------------------------------------- */

    public function test_the_sign_up_page_offers_both_options(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(__('email_auth.tab_phone'))
            ->assertSee(__('email_auth.tab_email'))
            ->assertSee('id="otpLoginPanel"', false)
            ->assertSee('id="emailRegisterPanel"', false)
            ->assertSee(route('register.email'), false)
            ->assertSee(route('register.email.verify'), false);
    }

    public function test_the_email_tab_can_be_opened_directly(): void
    {
        $html = $this->get(route('register', ['method' => 'email']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="authMethodEmail"[^>]*checked/', $html);
    }

    /* ---- Step 1: nothing is written until the code is confirmed ----------- */

    public function test_valid_details_email_a_code_but_create_nothing_yet(): void
    {
        Mail::fake();

        $this->step1(['email' => '  Layla@Example.com '])
            ->assertOk()
            ->assertJson(['display' => 'l***@example.com'])
            ->assertJsonStructure(['message', 'display', 'expires_in']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) {
            return $mail->hasTo(self::EMAIL) && preg_match('/^\d{6}$/', $mail->code) === 1;
        });
    }

    public function test_step_one_validates_its_input_and_sends_nothing_on_failure(): void
    {
        Mail::fake();

        $this->step1(['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->step1(['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->step1(['password' => 'short1', 'password_confirmation' => 'short1'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->step1(['password' => 'nodigitshere', 'password_confirmation' => 'nodigitshere'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->step1(['password_confirmation' => 'Different1'])->assertStatus(422)->assertJsonValidationErrors('password');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_email_that_already_has_an_account_is_refused(): void
    {
        User::factory()->create(['email' => self::EMAIL]);
        Mail::fake();

        $this->step1()->assertStatus(422)->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_a_send_failure_is_reported_and_nothing_is_left_pending(): void
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('smtp down'));

        $this->step1()->assertStatus(503)->assertJson(['message' => __('email_auth.errors.send_failed')]);

        $this->assertDatabaseCount('email_codes', 0);
    }

    /* ---- Step 2: the code creates and signs in ----------------------------- */

    public function test_the_right_code_creates_the_account_and_signs_in(): void
    {
        $code = $this->requestCode();

        $response = $this->step2($code)->assertOk()->assertJson(['redirect' => route('account.dashboard')]);

        $user = User::where('email', self::EMAIL)->first();
        $this->assertNotNull($user);
        $this->assertSame(self::NAME, $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNull($user->phone);
        $this->assertNotNull($user->referral_code);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(
            collect($response->baseResponse->headers->getCookies())->contains(function ($cookie) {
                return strpos($cookie->getName(), 'remember_web') === 0;
            }),
            'a remember-me cookie is issued'
        );
    }

    public function test_the_customer_lands_where_they_were_headed(): void
    {
        $code = $this->requestCode();

        $this->withSession(['url.intended' => route('checkout.address')])
            ->postJson(route('register.email.verify'), ['code' => $code])
            ->assertOk()
            ->assertJson(['redirect' => route('checkout.address')]);
    }

    public function test_a_referral_code_rewards_both_sides(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'AROMA-MAHDI']);
        $code = $this->requestCode(['referral_code' => 'aroma-mahdi']);

        $this->step2($code)->assertOk();

        $newUser = User::where('email', self::EMAIL)->first();
        $this->assertSame($referrer->id, $newUser->referred_by_user_id);
        $this->assertEquals(100, $newUser->loyalty_points);
        $this->assertEquals(100, $referrer->fresh()->loyalty_points);
        $this->assertDatabaseHas('point_transactions', ['user_id' => $newUser->id, 'type' => PointTransaction::TYPE_REFERRAL_SIGNUP_BONUS]);
    }

    public function test_an_unrecognized_referral_code_does_not_block_signup(): void
    {
        $code = $this->requestCode(['referral_code' => 'NOT-A-REAL-CODE']);

        $this->step2($code)->assertOk();

        $newUser = User::where('email', self::EMAIL)->first();
        $this->assertNull($newUser->referred_by_user_id);
    }

    public function test_a_shared_referral_link_prefills_the_email_panel_too(): void
    {
        User::factory()->create(['referral_code' => 'AROMA-LINKED']);

        $html = $this->get(route('register', ['ref' => 'AROMA-LINKED']))->assertOk()->getContent();

        $this->assertStringContainsString('AROMA-LINKED', $html);
        $this->assertSame('AROMA-LINKED', session('referral_code_prefill'));
    }

    public function test_a_code_alone_does_nothing_without_step_one_in_this_session(): void
    {
        $code = $this->requestCode();

        $this->flushSession();
        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.register.session_expired')]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_wrong_code_is_rejected_and_the_right_one_still_works(): void
    {
        $code = $this->requestCode();

        $this->step2($this->wrong($code))->assertStatus(422)->assertJson(['message' => __('otp.errors.invalid_code')]);
        $this->assertDatabaseCount('users', 0);

        $this->step2($code)->assertOk();
    }

    public function test_five_wrong_codes_burn_it_and_step_one_must_be_repeated(): void
    {
        $code = $this->requestCode();

        for ($i = 0; $i < 5; $i++) {
            $this->step2($this->wrong($code))->assertStatus(422);
        }
        $this->step2($this->wrong($code))->assertStatus(422)->assertJson(['message' => __('otp.errors.too_many_attempts')]);

        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.register.session_expired')]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $code = $this->requestCode();

        $this->travel(11)->minutes();

        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('otp.errors.expired')]);
    }

    public function test_the_half_finished_signup_expires_after_thirty_minutes(): void
    {
        $code = $this->requestCode();

        $this->travel(31)->minutes();

        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.register.session_expired')]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_address_being_claimed_between_the_two_steps_does_not_sign_into_that_account(): void
    {
        $code = $this->requestCode();
        $stranger = User::factory()->create(['email' => self::EMAIL]);

        $this->step2($code)->assertStatus(422)->assertJson(['message' => __('email_auth.register.session_expired')]);

        $this->assertGuest();
        $this->assertSame(1, User::where('email', self::EMAIL)->count());
        $this->assertSame($stranger->id, User::where('email', self::EMAIL)->value('id'));
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $code = $this->requestCode();

        $this->step2($code)->assertOk();
        auth()->logout();

        $this->step2($code)->assertStatus(422);
        $this->assertSame(1, User::count());
    }

    public function test_step_two_validates_the_code_format(): void
    {
        $this->requestCode();

        $this->step2('12ab56')->assertStatus(422)->assertJsonValidationErrors('code');
        $this->step2('12345')->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /* ---- Throttling --------------------------------------------------------- */

    public function test_repeated_sends_for_one_email_are_throttled(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->step1(['email' => self::EMAIL])->assertOk();
        }

        $this->step1(['email' => self::EMAIL])->assertStatus(429)->assertJson(['message' => __('otp.errors.rate_limited')]);
    }

    public function test_hammering_the_code_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->step2('123456')->assertStatus(422);
        }

        $this->step2('123456')->assertStatus(429)->assertJson(['message' => __('otp.errors.rate_limited')]);
    }

    /* ---- Doesn't disturb the other flows ------------------------------------ */

    public function test_the_password_registration_endpoint_still_does_not_exist(): void
    {
        $this->post('/register', $this->payload())->assertStatus(405);
    }

    public function test_sign_up_and_sign_in_stay_linked(): void
    {
        $this->get(route('register'))->assertSee(route('login'), false);
        $this->get(route('login'))->assertSee(route('register'), false);
    }
}
