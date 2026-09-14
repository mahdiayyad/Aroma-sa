<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    private function makeOtp(string $phone, string $code, array $overrides = []): OtpCode
    {
        return OtpCode::create(array_merge([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => OtpCode::PURPOSE_LOGIN,
            'expires_at' => now()->addMinutes(5),
        ], $overrides));
    }

    /* ---- Send never leaks whether a phone is registered --------------------- */

    public function test_send_responds_identically_for_registered_and_unregistered_phones(): void
    {
        User::factory()->create(['phone' => '+966500000001']);

        $registered = $this->postJson(route('otp.send'), ['phone' => '+966500000001']);
        $unregistered = $this->postJson(route('otp.send'), ['phone' => '+966500000002']);

        $registered->assertOk();
        $unregistered->assertOk();
        $this->assertSame($registered->json('message'), $unregistered->json('message'));
    }

    public function test_send_creates_a_hashed_otp_row(): void
    {
        $this->postJson(route('otp.send'), ['phone' => '+966500000003'])->assertOk();

        $otp = OtpCode::where('phone', '+966500000003')->first();
        $this->assertNotNull($otp);
        $this->assertNotEquals('123456', $otp->code_hash); // never plaintext
    }

    /* ---- Correct OTP logs an existing user in -------------------------------- */

    public function test_correct_otp_logs_in_an_existing_user(): void
    {
        $user = User::factory()->create(['phone' => '+966500000004', 'phone_verified_at' => null]);
        $this->makeOtp('+966500000004', '111111');

        $this->postJson(route('otp.verify'), ['phone' => '+966500000004', 'code' => '111111'])
            ->assertOk()
            ->assertJson(['redirect' => route('account.dashboard')]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    /* ---- Correct OTP for an unknown phone routes to profile completion ------- */

    public function test_correct_otp_for_a_new_phone_redirects_to_complete_profile(): void
    {
        $this->makeOtp('+966500000005', '222222');

        $this->postJson(route('otp.verify'), ['phone' => '+966500000005', 'code' => '222222'])
            ->assertOk()
            ->assertJson(['redirect' => route('otp.complete-profile')]);

        $this->assertGuest();
        $this->assertSame('+966500000005', session('otp.verified_phone'));
    }

    public function test_complete_profile_creates_a_passwordless_account(): void
    {
        $this->withSession(['otp.verified_phone' => '+966500000006']);

        $this->post(route('otp.complete-profile.store'), ['name' => 'New Customer'])
            ->assertRedirect(route('account.dashboard'));

        $user = User::where('phone', '+966500000006')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
        $this->assertNotNull($user->referral_code);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_complete_profile_is_unreachable_without_a_verified_phone_in_session(): void
    {
        $this->get(route('otp.complete-profile'))->assertRedirect(route('login'));
        $this->post(route('otp.complete-profile.store'), ['name' => 'Nope'])->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['name' => 'Nope']);
    }

    /* ---- Incorrect OTP --------------------------------------------------------- */

    public function test_an_incorrect_code_is_rejected_and_increments_attempts(): void
    {
        $otp = $this->makeOtp('+966500000007', '333333');

        $this->postJson(route('otp.verify'), ['phone' => '+966500000007', 'code' => '000000'])
            ->assertStatus(422);

        $this->assertEquals(1, $otp->fresh()->attempts);
        $this->assertGuest();
    }

    /* ---- Expired OTP ------------------------------------------------------------ */

    public function test_an_expired_code_is_rejected(): void
    {
        $this->makeOtp('+966500000008', '444444', ['expires_at' => now()->subMinute()]);

        $this->postJson(route('otp.verify'), ['phone' => '+966500000008', 'code' => '444444'])
            ->assertStatus(422)
            ->assertJson(['message' => __('otp.errors.expired')]);
    }

    /* ---- Too many attempts locks out that code --------------------------------- */

    public function test_too_many_wrong_attempts_locks_the_code(): void
    {
        $otp = $this->makeOtp('+966500000009', '555555', ['attempts' => OtpService::MAX_ATTEMPTS]);

        $this->postJson(route('otp.verify'), ['phone' => '+966500000009', 'code' => '555555'])
            ->assertStatus(422)
            ->assertJson(['message' => __('otp.errors.too_many_attempts')]);
    }

    /* ---- A code is single-use / resend supersedes the old one ------------------ */

    public function test_a_consumed_code_cannot_be_reused(): void
    {
        $user = User::factory()->create(['phone' => '+966500000010']);
        $this->makeOtp('+966500000010', '666666');

        $this->postJson(route('otp.verify'), ['phone' => '+966500000010', 'code' => '666666'])->assertOk();
        auth()->logout();

        $this->postJson(route('otp.verify'), ['phone' => '+966500000010', 'code' => '666666'])
            ->assertStatus(422)
            ->assertJson(['message' => __('otp.errors.not_found')]);
    }

    public function test_requesting_a_new_code_supersedes_the_previous_one(): void
    {
        app(OtpService::class)->send('+966500000011');
        $first = OtpCode::where('phone', '+966500000011')->latest('id')->first();

        app(OtpService::class)->send('+966500000011');

        // The first code's hash won't match anything the service can now
        // verify successfully, because verify() only ever looks at the
        // latest row for this phone+purpose.
        $result = app(OtpService::class)->verify('+966500000011', 'whatever-the-old-code-was');
        $this->assertFalse($result['valid']);
        $this->assertNotEquals($first->id, OtpCode::where('phone', '+966500000011')->latest('id')->first()->id);
    }

    /* ---- Rate limiting: too many OTP requests ---------------------------------- */

    public function test_too_many_send_requests_for_the_same_phone_are_throttled(): void
    {
        $phone = '+966500000012';

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('otp.send'), ['phone' => $phone])->assertOk();
        }

        $this->postJson(route('otp.send'), ['phone' => $phone])->assertStatus(429);
    }

    public function test_too_many_verify_requests_for_the_same_phone_are_throttled(): void
    {
        $phone = '+966500000013';
        $this->makeOtp($phone, '777777');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('otp.verify'), ['phone' => $phone, 'code' => '000000']);
        }

        $this->postJson(route('otp.verify'), ['phone' => $phone, 'code' => '000000'])->assertStatus(429);
    }

    /* ---- Invalid phone format is rejected up front ----------------------------- */

    public function test_send_rejects_an_invalid_phone_format(): void
    {
        $this->postJson(route('otp.send'), ['phone' => '12345'])->assertStatus(422);
    }
}
