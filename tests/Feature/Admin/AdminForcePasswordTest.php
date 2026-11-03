<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminForcePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithTempPassword(string $password = 'TempPass1'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STAFF,
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);
    }

    public function test_a_user_who_must_change_password_is_redirected_away_from_the_dashboard(): void
    {
        $this->actingAs($this->staffWithTempPassword())
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.password.change'));
    }

    public function test_a_user_who_must_change_password_is_redirected_away_from_any_other_admin_route(): void
    {
        $this->actingAs($this->staffWithTempPassword())
            ->get(route('admin.orders.index'))
            ->assertRedirect(route('admin.password.change'));
    }

    public function test_the_change_password_page_itself_does_not_redirect(): void
    {
        $this->actingAs($this->staffWithTempPassword())
            ->get(route('admin.password.change'))
            ->assertOk()
            ->assertSee(__('admin.change_password.forced_subtitle'));
    }

    public function test_a_normal_admin_without_the_flag_is_not_redirected(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_submitting_a_valid_new_password_clears_the_flag_and_reaches_the_dashboard(): void
    {
        $user = $this->staffWithTempPassword('TempPass1');

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'current_password' => 'TempPass1',
                'password' => 'NewPass2',
                'password_confirmation' => 'NewPass2',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewPass2', $user->password));

        // The flag being clear now actually unlocks the rest of the back-office.
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_the_wrong_current_password_is_rejected(): void
    {
        $user = $this->staffWithTempPassword('TempPass1');

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'current_password' => 'WrongPass1',
                'password' => 'NewPass2',
                'password_confirmation' => 'NewPass2',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_a_new_password_that_fails_confirmation_is_rejected(): void
    {
        $user = $this->staffWithTempPassword('TempPass1');

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'current_password' => 'TempPass1',
                'password' => 'NewPass2',
                'password_confirmation' => 'DoesNotMatch',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_a_weak_new_password_is_rejected(): void
    {
        $user = $this->staffWithTempPassword('TempPass1');

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'current_password' => 'TempPass1',
                'password' => 'alllowercase',
                'password_confirmation' => 'alllowercase',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_a_user_without_the_flag_can_still_change_their_password_voluntarily(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('OldPass1'),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.password.change'))
            ->assertOk()
            ->assertDontSee(__('admin.change_password.forced_subtitle'));

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'current_password' => 'OldPass1',
                'password' => 'NewPass2',
                'password_confirmation' => 'NewPass2',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Hash::check('NewPass2', $user->fresh()->password));
    }

    public function test_the_topbar_offers_a_change_password_link(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.password.change'), false);
    }

    public function test_a_newly_created_back_office_account_must_change_password_on_first_login(): void
    {
        $this->artisan('aroma:make-admin', [
            'email' => 'newstaff@aroma.sa',
            '--password' => 'TempPass1',
            '--role' => 'staff',
        ])->assertSuccessful();

        $user = User::where('email', 'newstaff@aroma.sa')->first();
        $this->assertTrue($user->must_change_password);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.password.change'));
    }

    public function test_promoting_an_existing_user_does_not_force_a_password_change(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'must_change_password' => false]);

        $this->artisan('aroma:make-admin', [
            'email' => $user->email,
            '--role' => 'staff',
        ])->assertSuccessful();

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
