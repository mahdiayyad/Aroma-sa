<?php

namespace Tests\Feature;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phone-number sign-in on top of the Tawked Verify API. Every test fakes the
 * HTTP layer — the real sandbox key has a 10-send lifetime cap and must never
 * be reached from the suite.
 */
class OtpTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+966500000004';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tawked.api_key' => 'tk_test_fake',
            'services.tawked.base_url' => 'https://tawked.com',
            'services.tawked.dev_code' => '',
        ]);
    }

    /* ---- helpers -------------------------------------------------------------- */

    /** Tawked hands back a fresh verification id per start — unless a test pins one. */
    private function tawkedStarts(?string $id = null): void
    {
        Http::fake([
            'tawked.com/v1/verify/start' => function () use ($id) {
                return Http::response([
                    'id' => $id ?? (string) Str::uuid(),
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(5)->toIso8601String(),
                ], 201);
            },
        ]);
    }

    private function tawkedChecks(array $body): void
    {
        Http::fake(['tawked.com/v1/verify/check' => Http::response($body, 200)]);
    }

    private function pending(string $phone = self::PHONE, string $id = 'ver-1', array $overrides = []): PhoneVerification
    {
        return PhoneVerification::create(array_merge([
            'phone' => $phone,
            'purpose' => PhoneVerification::PURPOSE_LOGIN,
            'tawked_id' => $id,
            'status' => PhoneVerification::STATUS_PENDING,
            'expires_at' => now()->addMinutes(5),
        ], $overrides));
    }

    private function verify(string $code = '123456', string $phone = self::PHONE)
    {
        return $this->postJson(route('otp.verify'), ['phone' => $phone, 'code' => $code]);
    }

    /* ---- send: what goes to Tawked, what we keep ------------------------------ */

    public function test_send_responds_identically_for_registered_and_unregistered_phones(): void
    {
        $this->tawkedStarts();
        User::factory()->create(['phone' => '+966500000001']);

        $registered = $this->postJson(route('otp.send'), ['phone' => '+966500000001']);
        $unregistered = $this->postJson(route('otp.send'), ['phone' => '+966500000002']);

        $registered->assertOk();
        $unregistered->assertOk();
        $this->assertSame($registered->json('message'), $unregistered->json('message'));
    }

    public function test_send_asks_tawked_for_an_sms_code_and_keeps_only_its_verification_id(): void
    {
        $this->tawkedStarts('ver-abc');

        $this->postJson(route('otp.send'), ['phone' => self::PHONE])->assertOk()->assertJsonStructure(['message', 'expires_in']);

        Http::assertSent(function (Request $request) {
            return Str::endsWith($request->url(), '/v1/verify/start')
                && $request->hasHeader('Authorization', 'Bearer tk_test_fake')
                && $request['to'] === self::PHONE
                && $request['channel'] === 'sms'
                && $request['reference'] === 'login'
                && $request['lang'] === 'ar'
                && $request['client_ip'] === '127.0.0.1';
        });

        $row = PhoneVerification::where('phone', self::PHONE)->first();
        $this->assertNotNull($row);
        $this->assertSame('ver-abc', $row->tawked_id);
        $this->assertSame(PhoneVerification::STATUS_PENDING, $row->status);

        // Nothing resembling a code is ever stored locally.
        $this->assertFalse(Schema::hasColumn('phone_verifications', 'code_hash'));
        $this->assertFalse(Schema::hasColumn('phone_verifications', 'code'));
    }

    public function test_send_asks_for_an_english_message_only_on_the_english_site(): void
    {
        $this->tawkedStarts();

        $this->withSession(['locale' => 'en'])->postJson(route('otp.send'), ['phone' => self::PHONE])->assertOk();

        Http::assertSent(function (Request $request) {
            return $request['lang'] === 'en';
        });
    }

    public function test_send_normalises_the_phone_before_calling_tawked(): void
    {
        $this->tawkedStarts();

        $this->postJson(route('otp.send'), ['phone' => '05 0000 0004'])->assertOk();

        Http::assertSent(function (Request $request) {
            return $request['to'] === self::PHONE;
        });
        $this->assertDatabaseHas('phone_verifications', ['phone' => self::PHONE]);
    }

    public function test_send_rejects_numbers_that_are_not_saudi_mobiles_without_calling_tawked(): void
    {
        Http::fake();

        foreach (['+971501234567', '+966112345678', '12345', 'abc'] as $bad) {
            $this->postJson(route('otp.send'), ['phone' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors('phone');
        }

        Http::assertNothingSent();
    }

    public function test_requesting_a_new_code_supersedes_the_previous_one(): void
    {
        $expires = now()->addMinutes(5)->toIso8601String();
        Http::fake([
            'tawked.com/v1/verify/start' => Http::sequence()
                ->push(['id' => 'ver-1', 'status' => 'pending', 'expires_at' => $expires], 201)
                ->push(['id' => 'ver-2', 'status' => 'pending', 'expires_at' => $expires], 201),
        ]);

        $this->postJson(route('otp.send'), ['phone' => self::PHONE])->assertOk();
        $this->postJson(route('otp.send'), ['phone' => self::PHONE])->assertOk();

        $this->assertSame(PhoneVerification::STATUS_SUPERSEDED, PhoneVerification::where('tawked_id', 'ver-1')->value('status'));
        $this->assertSame(PhoneVerification::STATUS_PENDING, PhoneVerification::where('tawked_id', 'ver-2')->value('status'));

        // Only the newest verification is ever checked.
        Http::fake(['tawked.com/v1/verify/check' => Http::response(['verified' => false, 'status' => 'invalid_code'], 200)]);
        $this->verify()->assertStatus(422);
        Http::assertSent(function (Request $request) {
            return Str::endsWith($request->url(), '/v1/verify/check') && $request['id'] === 'ver-2';
        });
    }

    /* ---- send: Tawked-side failures ------------------------------------------- */

    /** @dataProvider tawkedSendFailures */
    public function test_send_maps_tawked_failures_to_a_clean_response(int $httpStatus, array $body, int $expectedStatus): void
    {
        Http::fake(['tawked.com/v1/verify/start' => Http::response($body, $httpStatus)]);

        $this->postJson(route('otp.send'), ['phone' => self::PHONE])
            ->assertStatus($expectedStatus)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('phone_verifications', 0);
    }

    public function tawkedSendFailures(): array
    {
        return [
            'invalid destination' => [422, ['error' => 'invalid_destination'], 422],
            'sandbox unverified destination' => [422, ['error' => 'sandbox_unverified_destination'], 422],
            'per-destination rate limit' => [429, ['error' => 'rate_limited'], 429],
            'per-ip rate limit' => [429, ['error' => 'ip_rate_limited'], 429],
            'daily spend cap is our problem, not the customer' => [429, ['error' => 'spend_cap_reached'], 503],
            'sandbox quota exhausted' => [429, ['error' => 'sandbox_quota_exceeded'], 503],
            'no credit left' => [402, ['error' => 'insufficient_credits'], 503],
            'bad key' => [401, ['error' => 'unauthorized'], 503],
            'tawked is down' => [500, ['error' => 'server_error'], 503],
        ];
    }

    public function test_send_reports_unavailable_when_tawked_cannot_be_reached(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $this->postJson(route('otp.send'), ['phone' => self::PHONE])->assertStatus(503);
        $this->assertDatabaseCount('phone_verifications', 0);
    }

    public function test_send_fails_closed_when_no_api_key_is_configured(): void
    {
        config(['services.tawked.api_key' => '']);
        Http::fake();

        $this->postJson(route('otp.send'), ['phone' => self::PHONE])->assertStatus(503);

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_a_failed_send_does_not_invalidate_the_code_the_customer_already_holds(): void
    {
        $this->pending(self::PHONE, 'ver-1');
        Http::fake(['tawked.com/v1/verify/start' => Http::response(['error' => 'server_error'], 500)]);

        $this->postJson(route('otp.send'), ['phone' => self::PHONE])->assertStatus(503);

        $this->assertSame(PhoneVerification::STATUS_PENDING, PhoneVerification::where('tawked_id', 'ver-1')->value('status'));
    }

    /* ---- verify: a correct code ----------------------------------------------- */

    public function test_correct_code_logs_in_an_existing_user_and_marks_the_phone_verified(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'phone_verified_at' => null]);
        $this->pending();
        $this->tawkedChecks(['verified' => true, 'status' => 'verified']);

        $this->verify()->assertOk()->assertJson(['redirect' => route('account.dashboard')]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertSame(PhoneVerification::STATUS_VERIFIED, PhoneVerification::first()->status);
        Http::assertSent(function (Request $request) {
            return Str::endsWith($request->url(), '/v1/verify/check') && $request['id'] === 'ver-1' && $request['code'] === '123456';
        });
    }

    public function test_an_existing_user_lands_where_they_were_headed_not_on_the_dashboard(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $this->pending();
        $this->tawkedChecks(['verified' => true, 'status' => 'verified']);

        $this->withSession(['url.intended' => route('checkout.address')])
            ->postJson(route('otp.verify'), ['phone' => self::PHONE, 'code' => '123456'])
            ->assertOk()
            ->assertJson(['redirect' => route('checkout.address')]);
    }

    public function test_a_legacy_email_and_password_account_can_sign_in_with_its_verified_phone(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'email' => 'legacy@example.com']);
        $this->pending();
        $this->tawkedChecks(['verified' => true, 'status' => 'verified']);

        $this->verify()->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_disabled_account_cannot_sign_in(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'is_active' => false]);
        $this->pending();
        $this->tawkedChecks(['verified' => true, 'status' => 'verified']);

        $this->verify()->assertStatus(403)->assertJsonStructure(['message']);

        $this->assertGuest();
    }

    public function test_correct_code_for_a_new_phone_redirects_to_complete_profile(): void
    {
        $this->pending();
        $this->tawkedChecks(['verified' => true, 'status' => 'verified']);

        $this->verify()->assertOk()->assertJson(['redirect' => route('otp.complete-profile')]);

        $this->assertGuest();
        $this->assertSame(self::PHONE, session('otp.verified_phone'));
        $this->assertDatabaseMissing('users', ['phone' => self::PHONE]);
    }

    /* ---- verify: wrong / expired / exhausted ---------------------------------- */

    public function test_an_incorrect_code_is_rejected_and_can_be_retried(): void
    {
        $this->pending();
        $this->tawkedChecks(['verified' => false, 'status' => 'invalid_code', 'attempts_remaining' => 2]);

        $this->verify('000000')
            ->assertStatus(422)
            ->assertJson(['message' => __('otp.errors.invalid_code')]);

        $this->assertGuest();
        $this->assertSame(PhoneVerification::STATUS_PENDING, PhoneVerification::first()->status);
    }

    public function test_exhausted_attempts_close_the_verification(): void
    {
        $this->pending();
        $this->tawkedChecks(['verified' => false, 'status' => 'too_many_attempts', 'attempts_remaining' => 0]);

        $this->verify()->assertStatus(422)->assertJson(['message' => __('otp.errors.too_many_attempts')]);

        $this->assertSame(PhoneVerification::STATUS_FAILED, PhoneVerification::first()->status);

        // …and it can't be probed further without another request to Tawked.
        Http::fake();
        $this->verify()->assertStatus(422)->assertJson(['message' => __('otp.errors.not_found')]);
        Http::assertNothingSent();
    }

    public function test_a_code_tawked_reports_as_expired_is_rejected(): void
    {
        $this->pending();
        $this->tawkedChecks(['verified' => false, 'status' => 'expired']);

        $this->verify()->assertStatus(422)->assertJson(['message' => __('otp.errors.expired')]);

        $this->assertSame(PhoneVerification::STATUS_EXPIRED, PhoneVerification::first()->status);
    }

    public function test_a_locally_expired_code_is_rejected_without_calling_tawked(): void
    {
        $this->pending(self::PHONE, 'ver-1', ['expires_at' => now()->subMinute()]);
        Http::fake();

        $this->verify()->assertStatus(422)->assertJson(['message' => __('otp.errors.expired')]);

        Http::assertNothingSent();
        $this->assertSame(PhoneVerification::STATUS_EXPIRED, PhoneVerification::first()->status);
    }

    public function test_a_canceled_or_failed_verification_reads_as_not_found(): void
    {
        $this->pending();
        $this->tawkedChecks(['verified' => false, 'status' => 'canceled']);

        $this->verify()->assertStatus(422)->assertJson(['message' => __('otp.errors.not_found')]);
    }

    public function test_verifying_without_ever_requesting_a_code_reads_as_not_found(): void
    {
        Http::fake();

        $this->verify()->assertStatus(422)->assertJson(['message' => __('otp.errors.not_found')]);

        Http::assertNothingSent();
    }

    public function test_a_used_code_cannot_be_replayed(): void
    {
        $this->pending();
        $this->tawkedChecks(['verified' => true, 'status' => 'verified']);

        $this->verify()->assertOk();
        $this->verify()->assertStatus(422)->assertJson(['message' => __('otp.errors.not_found')]);

        Http::assertSentCount(1); // the replay never even reached Tawked
    }

    public function test_a_tawked_outage_while_checking_leaves_the_code_retryable(): void
    {
        $this->pending();
        Http::fake(['tawked.com/v1/verify/check' => Http::response(['error' => 'server_error'], 500)]);

        $this->verify()->assertStatus(503)->assertJson(['message' => __('otp.errors.unavailable')]);

        $this->assertGuest();
        $this->assertSame(PhoneVerification::STATUS_PENDING, PhoneVerification::first()->status);
    }

    public function test_verify_validates_the_code_format(): void
    {
        Http::fake();

        $this->verify('12ab56')->assertStatus(422)->assertJsonValidationErrors('code');
        $this->verify('12345')->assertStatus(422)->assertJsonValidationErrors('code');

        Http::assertNothingSent();
    }

    /* ---- new-account step ----------------------------------------------------- */

    public function test_complete_profile_creates_a_phone_only_account_from_just_a_name(): void
    {
        $this->withSession(['otp.verified_phone' => '+966500000006']);

        $this->post(route('otp.complete-profile.store'), ['name' => 'New Customer'])
            ->assertRedirect(route('account.dashboard'));

        $user = User::where('phone', '+966500000006')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
        $this->assertNull($user->email);
        $this->assertNotNull($user->referral_code);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_complete_profile_ignores_extra_fields_it_no_longer_collects(): void
    {
        $this->withSession(['otp.verified_phone' => '+966500000006']);

        $this->post(route('otp.complete-profile.store'), ['name' => 'New Customer', 'email' => 'x@example.com', 'gender' => 'female']);

        $user = User::where('phone', '+966500000006')->first();
        $this->assertNull($user->email);
        $this->assertSame('unspecified', $user->gender);
    }

    public function test_complete_profile_requires_a_name(): void
    {
        $this->withSession(['otp.verified_phone' => '+966500000006']);

        $this->post(route('otp.complete-profile.store'), ['name' => ''])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['phone' => '+966500000006']);
    }

    public function test_complete_profile_is_unreachable_without_a_verified_phone_in_session(): void
    {
        $this->get(route('otp.complete-profile'))->assertRedirect(route('login'));
        $this->post(route('otp.complete-profile.store'), ['name' => 'Nope'])->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['name' => 'Nope']);
    }

    public function test_finishing_signup_twice_for_the_same_number_signs_into_the_one_account(): void
    {
        $existing = User::factory()->create(['phone' => '+966500000006']);
        $this->withSession(['otp.verified_phone' => '+966500000006']);

        $this->post(route('otp.complete-profile.store'), ['name' => 'Second Tab'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertSame(1, User::where('phone', '+966500000006')->count());
        $this->assertAuthenticatedAs($existing);
    }

    /* ---- rate limiting -------------------------------------------------------- */

    public function test_too_many_send_requests_for_the_same_phone_are_throttled(): void
    {
        $this->tawkedStarts();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('otp.send'), ['phone' => self::PHONE])->assertOk();
        }

        $this->postJson(route('otp.send'), ['phone' => self::PHONE])
            ->assertStatus(429)
            ->assertJson(['message' => __('otp.errors.rate_limited')]);
    }

    public function test_writing_the_same_number_differently_does_not_buy_extra_sends(): void
    {
        $this->tawkedStarts();

        foreach (['+966500000004', '0500000004', '966500000004'] as $variant) {
            $this->postJson(route('otp.send'), ['phone' => $variant])->assertOk();
        }

        $this->postJson(route('otp.send'), ['phone' => '+966 50 000 0004'])->assertStatus(429);
    }

    public function test_too_many_verify_requests_for_the_same_phone_are_throttled(): void
    {
        $this->pending();
        $this->tawkedChecks(['verified' => false, 'status' => 'invalid_code', 'attempts_remaining' => 1]);

        for ($i = 0; $i < 10; $i++) {
            $this->verify('000000')->assertStatus(422);
        }

        $this->verify('000000')->assertStatus(429);
    }
}
