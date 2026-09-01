<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the privilege-escalation bug: User::isAdmin()
 * treats 'staff' and 'admin' identically, and the customer-edit form let
 * any caller who cleared the ['auth','admin'] route middleware (i.e. any
 * staff account too) set any user's role to admin — including their own —
 * with no extra check. See Admin\CustomerController::update.
 */
class AdminCustomerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_STAFF]);
    }

    public function test_a_staff_account_cannot_promote_another_user_to_admin(): void
    {
        $staff = $this->staff();
        $target = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($staff)->patch(route('admin.customers.update', $target), [
            'role'      => User::ROLE_ADMIN,
            'is_active' => '1',
        ])->assertRedirect(route('admin.customers.show', $target))->assertSessionHas('error');

        $this->assertSame(User::ROLE_CUSTOMER, $target->fresh()->role);
    }

    public function test_a_staff_account_cannot_promote_itself(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->patch(route('admin.customers.update', $staff), [
            'role'      => User::ROLE_ADMIN,
            'is_active' => '1',
        ])->assertRedirect(route('admin.customers.show', $staff))->assertSessionHas('error');

        $this->assertSame(User::ROLE_STAFF, $staff->fresh()->role);
    }

    public function test_an_admin_cannot_change_their_own_role_either(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.customers.update', $admin), [
            'role'      => User::ROLE_STAFF,
            'is_active' => '1',
        ])->assertSessionHas('error');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_an_admin_can_change_another_users_role(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($admin)->patch(route('admin.customers.update', $target), [
            'role'      => User::ROLE_STAFF,
            'is_active' => '1',
        ])->assertRedirect(route('admin.customers.show', $target))->assertSessionHas('status');

        $this->assertSame(User::ROLE_STAFF, $target->fresh()->role);
    }

    public function test_a_staff_account_can_still_update_non_role_fields(): void
    {
        // The gate is scoped to an actual role *change* — a staff account
        // saving the form with the role left as-is (loyalty/active only)
        // must not be blocked.
        $staff = $this->staff();
        $target = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'loyalty_points' => 0]);

        $this->actingAs($staff)->patch(route('admin.customers.update', $target), [
            'role'           => User::ROLE_CUSTOMER, // unchanged
            'loyalty_points' => 50,
            'is_active'      => '1',
        ])->assertSessionHas('status');

        $this->assertSame(50, $target->fresh()->loyalty_points);
    }
}
