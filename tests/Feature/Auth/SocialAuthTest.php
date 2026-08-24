<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOauthUser(string $id, ?string $email, ?string $name = 'Jane Doe', ?string $avatar = 'https://avatar.example/jane.png'): SocialiteUserContract
    {
        $user = Mockery::mock(SocialiteUserContract::class);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);
        $user->shouldReceive('getAvatar')->andReturn($avatar);

        return $user;
    }

    private function mockDriverUser(string $provider, SocialiteUserContract $user): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    private function configureGoogle(): void
    {
        config(['services.google.client_id' => 'test-google-id', 'services.google.client_secret' => 'secret']);
    }

    /** The real .env may have live Google credentials — clear them explicitly. */
    private function unconfigureGoogle(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    }

    /* ---- Redirect / availability gating ------------------------------------ */

    public function test_google_redirect_sends_the_user_to_googles_authorization_screen(): void
    {
        $this->configureGoogle();

        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth?client_id=test'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('social.redirect', 'google'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth?client_id=test');
    }

    public function test_an_unconfigured_provider_fails_gracefully_without_touching_socialite(): void
    {
        $this->unconfigureGoogle();

        // The whole point is that Socialite must never even be asked to
        // resolve a driver in this state.
        Socialite::shouldReceive('driver')->never();

        $this->get(route('social.redirect', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    public function test_an_unsupported_provider_name_is_rejected(): void
    {
        $this->get(route('social.redirect', 'facebook'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    /* ---- The reported bug: error must not land on the login field ---------- */

    public function test_a_social_failure_does_not_populate_the_logins_field_error(): void
    {
        $this->unconfigureGoogle();

        $this->get(route('social.redirect', 'google'));

        $errors = session('errors');
        $this->assertTrue($errors->has('social'));
        $this->assertFalse($errors->has('login'));
    }

    /* ---- Callback: account resolution -------------------------------------- */

    public function test_a_new_user_is_created_and_logged_in_on_first_sign_in(): void
    {
        $this->configureGoogle();
        $this->mockDriverUser('google', $this->fakeOauthUser('g-123', 'newperson@example.com', 'New Person'));

        Event::fake([Login::class]);

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'newperson@example.com',
            'provider' => 'google',
            'provider_id' => 'g-123',
            'name' => 'New Person',
        ]);

        $user = User::where('email', 'newperson@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_existing_social_identity_logs_in_without_duplicating_the_account(): void
    {
        $this->configureGoogle();
        $existing = User::factory()->create(['provider' => 'google', 'provider_id' => 'g-999', 'email' => 'known@example.com']);
        $this->mockDriverUser('google', $this->fakeOauthUser('g-999', 'known@example.com'));

        $this->get(route('social.callback', 'google'))->assertRedirect(route('account.dashboard'));

        $this->assertSame(1, User::where('email', 'known@example.com')->count());
        $this->assertAuthenticatedAs($existing);
    }

    public function test_a_matching_email_links_the_existing_password_account_instead_of_duplicating_it(): void
    {
        $this->configureGoogle();
        $existing = User::factory()->create(['email' => 'sara@example.com', 'provider' => null, 'provider_id' => null]);
        $this->mockDriverUser('google', $this->fakeOauthUser('g-777', 'sara@example.com'));

        $this->get(route('social.callback', 'google'))->assertRedirect(route('account.dashboard'));

        $this->assertSame(1, User::where('email', 'sara@example.com')->count());
        $this->assertAuthenticatedAs($existing);
        $this->assertSame('google', $existing->fresh()->provider);
        $this->assertSame('g-777', $existing->fresh()->provider_id);
    }

    public function test_a_name_missing_from_the_provider_falls_back_to_the_email_prefix(): void
    {
        $this->configureGoogle();
        $this->mockDriverUser('google', $this->fakeOauthUser('g-555', 'quiet.person@example.com', null));

        $this->get(route('social.callback', 'google'));

        $this->assertDatabaseHas('users', ['email' => 'quiet.person@example.com', 'name' => 'quiet.person']);
    }

    public function test_a_failed_token_exchange_redirects_back_with_a_social_error(): void
    {
        $this->configureGoogle();
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andThrow(new \Exception('invalid_grant'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
    }

    /* ---- Returning to the page the shopper actually started from ----------- */

    public function test_a_failure_from_the_register_page_returns_to_register_not_login(): void
    {
        $this->unconfigureGoogle();

        $this->withSession(['_previous' => ['url' => route('register')]])
            ->get(route('social.redirect', 'google'))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('social');
    }
}
