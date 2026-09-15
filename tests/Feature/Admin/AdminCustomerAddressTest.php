<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin customer-show page's "Saved Addresses" card. Regression coverage
 * for a real bug caught during design review: <x-address-summary> only
 * accepts a plain array ('is_array($address) ? $address : []'), so passing
 * an Eloquent Address model directly renders every field blank with no
 * error — the view must call ->toArray() explicitly.
 */
class AdminCustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_customer_page_shows_saved_addresses_with_method_badges(): void
    {
        $customer = User::factory()->create();

        Address::create([
            'user_id' => $customer->id,
            'type' => 'shipping',
            'recipient_name' => 'Sara Al Qahtani',
            'phone' => '+966500000000',
            'method' => Address::METHOD_NATIONAL_CODE,
            'location_code' => 'RAHA1234',
            'formatted_address' => 'Al Olaya, Riyadh, Riyadh Region (RAHA1234)',
            'city' => 'Riyadh',
            'region' => 'Riyadh Region',
            'is_default' => true,
        ]);

        Address::create([
            'user_id' => $customer->id,
            'type' => 'shipping',
            'recipient_name' => 'Sara Al Qahtani',
            'phone' => '+966500000000',
            'method' => Address::METHOD_MANUAL,
            'location_code' => null,
            'country' => 'SA',
            'city' => 'Jeddah',
            'district' => 'Al Rawdah',
            'street_address' => 'King Fahd Road',
            'building_number' => '1234',
            'is_default' => false,
        ]);

        $this->actingAs($this->admin())->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee(__('location.method.code'))
            ->assertSee(__('location.method.manual'))
            ->assertSee('King Fahd Road')
            ->assertSee('1234');
    }

    public function test_admin_customer_page_shows_an_empty_state_with_no_addresses(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->admin())->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee(__('admin.customers.no_addresses'));
    }
}
