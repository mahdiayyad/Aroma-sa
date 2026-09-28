<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer sign-in is a mobile number (Tawked OTP, see OtpTest) or email +
 * password with an emailed code (see EmailLoginTest). These tests pin the page
 * itself and what stays retired: form-posted passwords and password-reset links.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_offers_phone_first_and_email_as_the_second_option(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-otp-panel', false)
            ->assertSee('data-only-saudi', false)
            ->assertSee('data-otp-mode="email"', false)
            ->assertDontSee('name="login"', false);
    }

    public function test_a_plain_password_form_post_to_login_still_does_not_exist(): void
    {
        User::factory()->create(['email' => 'noura@example.com']);

        $this->post('/login', ['login' => 'noura@example.com', 'password' => 'password'])->assertStatus(405);

        $this->assertGuest();
    }

    public function test_password_reset_routes_no_longer_exist(): void
    {
        $this->get('/password/forgot')->assertNotFound();
        $this->post('/password/forgot', ['email' => 'a@example.com'])->assertNotFound();
    }

    public function test_an_authenticated_user_is_sent_away_from_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('root'));
        $this->assertGuest();
    }

    public function test_a_referral_link_keeps_its_code_through_the_login_page(): void
    {
        $this->get('/login?ref=AROMA-ABC123')->assertOk();

        $this->assertSame('AROMA-ABC123', session('referral_code_prefill'));
    }

    public function test_a_malformed_referral_code_is_ignored(): void
    {
        $this->get('/login?ref='.urlencode('<script>alert(1)</script>'))->assertOk();

        $this->assertNull(session('referral_code_prefill'));
    }
}
