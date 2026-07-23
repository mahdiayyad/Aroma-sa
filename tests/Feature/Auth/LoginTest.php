<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email'    => 'noura@example.com',
            'password' => Hash::make('Passw0rd1'),
        ]);

        $response = $this->post('/login', ['login' => 'noura@example.com', 'password' => 'Passw0rd1']);

        $response->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_phone(): void
    {
        $user = User::factory()->create([
            'email'    => null,
            'phone'    => '0555555555',
            'password' => Hash::make('Passw0rd1'),
        ]);

        $this->post('/login', ['login' => '0555555555', 'password' => 'Passw0rd1']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email'    => 'x@example.com',
            'password' => Hash::make('Passw0rd1'),
        ]);

        $this->post('/login', ['login' => 'x@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('root'));
        $this->assertGuest();
    }
}
