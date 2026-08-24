<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_renders(): void
    {
        $this->get('/register')->assertOk()->assertSee('dir="', false);
    }

    public function test_new_user_can_register_with_phone(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Layla Ahmed',
            'phone'                 => '+966512345678',
            'password'              => 'Passw0rd1',
            'password_confirmation' => 'Passw0rd1',
            'gender'                => 'female',
        ]);

        $response->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['name' => 'Layla Ahmed', 'phone' => '+966512345678']);
    }

    public function test_registration_requires_email_or_phone(): void
    {
        $this->post('/register', [
            'name'                  => 'No Identifier',
            'password'              => 'Passw0rd1',
            'password_confirmation' => 'Passw0rd1',
        ])->assertSessionHasErrors(['email', 'phone']);

        $this->assertGuest();
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->post('/register', [
            'name'                  => 'Bad Phone',
            'phone'                 => '12345',
            'password'              => 'Passw0rd1',
            'password_confirmation' => 'Passw0rd1',
        ])->assertSessionHasErrors('phone');
    }
}
