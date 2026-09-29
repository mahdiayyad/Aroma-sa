<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sign-up page is the "create your account" face of the phone-number code
 * flow (the verification itself is covered in OtpTest); there is no password
 * form to post. These tests cover the page, its links and the referral hand-off.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_up_page_renders_the_phone_flow(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee(__('auth_ui.register.title'))
            ->assertSee('id="otpLoginPanel"', false)
            ->assertSee(route('otp.send'), false)
            ->assertSee('data-only-saudi', false)
            ->assertDontSee('id="emailLoginPanel"', false);
    }

    public function test_the_sign_up_page_links_back_to_sign_in_for_people_who_already_have_an_account(): void
    {
        $this->get('/register')
            ->assertSee(__('auth_ui.register.have_account'))
            ->assertSee('href="'.route('login').'"', false);
    }

    public function test_the_sign_in_page_links_to_sign_up(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(__('auth_ui.login.no_account'))
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_a_referral_code_survives_the_hop_from_sign_in_to_sign_up(): void
    {
        $this->get('/login?ref=AROMA-ABC123')
            ->assertSee('href="'.route('register', ['ref' => 'AROMA-ABC123']).'"', false);
    }

    public function test_a_shared_referral_link_keeps_its_code(): void
    {
        $this->get('/register?ref=AROMA-ABC123')->assertOk();

        $this->assertSame('AROMA-ABC123', session('referral_code_prefill'));
    }

    public function test_a_malformed_referral_code_is_ignored(): void
    {
        $this->get('/register?ref='.urlencode('<script>alert(1)</script>'))->assertOk();

        $this->assertNull(session('referral_code_prefill'));
    }

    public function test_the_password_registration_endpoint_still_does_not_exist(): void
    {
        $this->post('/register', [
            'name' => 'Layla Ahmed',
            'phone' => '+966512345678',
            'password' => 'Passw0rd1',
            'password_confirmation' => 'Passw0rd1',
        ])->assertStatus(405);

        $this->assertDatabaseMissing('users', ['name' => 'Layla Ahmed']);
    }

    public function test_someone_already_signed_in_is_sent_away_from_the_sign_up_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/register')->assertRedirect();
    }
}
