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

    /* ---- Phone number = sign-in credential: support can set/fix it ---------- */

    public function test_an_admin_can_set_a_missing_phone_number_on_a_legacy_account(): void
    {
        $customer = User::factory()->create(['phone' => null, 'phone_verified_at' => now()]);

        $this->actingAs($this->admin())->patch(route('admin.customers.update', $customer), [
            'role' => User::ROLE_CUSTOMER,
            'is_active' => '1',
            'phone' => '0570574471',
        ])->assertSessionHas('status');

        $this->assertSame('+966570574471', $customer->fresh()->phone);
        $this->assertNull($customer->fresh()->phone_verified_at, 'an admin-typed number is unproven until the owner signs in');
    }

    public function test_an_admin_phone_edit_must_be_a_saudi_mobile_and_not_already_taken(): void
    {
        $customer = User::factory()->create(['phone' => null]);
        User::factory()->create(['phone' => '+966570574471']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
            'role' => User::ROLE_CUSTOMER, 'phone' => '+971501234567',
        ])->assertSessionHasErrors('phone');

        $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
            'role' => User::ROLE_CUSTOMER, 'phone' => '+966570574471',
        ])->assertSessionHasErrors('phone');

        $this->assertNull($customer->fresh()->phone);
    }

    public function test_re_saving_a_customer_with_a_non_saudi_stored_number_does_not_trip_the_saudi_rule(): void
    {
        $customer = User::factory()->create(['phone' => '+971501234567']);

        $this->actingAs($this->admin())->patch(route('admin.customers.update', $customer), [
            'role' => User::ROLE_CUSTOMER,
            'is_active' => '1',
            'phone' => '+971501234567',
            'loyalty_points' => 25,
        ])->assertSessionHas('status');

        $this->assertSame('+971501234567', $customer->fresh()->phone);
        $this->assertSame(25, $customer->fresh()->loyalty_points);
    }

    public function test_omitting_phone_and_email_leaves_them_untouched(): void
    {
        $customer = User::factory()->create(['phone' => '+966570574471', 'email' => 'keep@example.com']);

        $this->actingAs($this->admin())->patch(route('admin.customers.update', $customer), [
            'role' => User::ROLE_CUSTOMER, 'is_active' => '1',
        ]);

        $this->assertSame('+966570574471', $customer->fresh()->phone);
        $this->assertSame('keep@example.com', $customer->fresh()->email);
    }

    public function test_the_customer_pages_cope_with_phone_only_and_email_only_accounts(): void
    {
        $admin = $this->admin();
        $phoneOnly = User::factory()->create(['email' => null, 'phone' => '+966570574471']);
        $emailOnly = User::factory()->create(['email' => 'legacy@example.com', 'phone' => null]);

        $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk()->assertSee('+966570574471')->assertSee('legacy@example.com');
        $this->actingAs($admin)->get(route('admin.customers.show', $phoneOnly))->assertOk();
        $this->actingAs($admin)->get(route('admin.customers.show', $emailOnly))->assertOk();
    }
}
