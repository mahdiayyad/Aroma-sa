<?php

namespace Tests\Feature\Account;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    private array $payload = [
        'label' => 'Home',
        'recipient_name' => 'Sara Al Qahtani',
        'phone' => '+966500000000',
        'location_code' => 'RAHA1234',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLocationLookup();
    }

    public function test_guest_is_redirected_from_the_address_book(): void
    {
        $this->get(route('account.addresses.index'))->assertRedirect('/login');
    }

    public function test_a_user_can_add_an_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->payload)
            ->assertRedirect(route('account.addresses.index'));

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'recipient_name' => 'Sara Al Qahtani',
            'type' => 'shipping',
        ]);
    }

    public function test_the_first_address_becomes_the_default_automatically(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->payload);

        $this->assertTrue(Address::first()->is_default);
    }

    public function test_only_one_address_can_be_default_at_a_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.addresses.store'), $this->payload);

        $this->actingAs($user)->post(route('account.addresses.store'), array_merge($this->payload, [
            'recipient_name' => 'Second Address',
            'is_default' => '1',
        ]));

        $this->assertSame(1, Address::where('user_id', $user->id)->where('is_default', true)->count());
        $this->assertSame('Second Address', Address::where('is_default', true)->first()->recipient_name);
    }

    public function test_a_user_can_set_a_different_address_as_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.addresses.store'), $this->payload);
        $this->actingAs($user)->post(route('account.addresses.store'), array_merge($this->payload, ['recipient_name' => 'Second']));

        $second = Address::where('recipient_name', 'Second')->first();

        $this->actingAs($user)->patch(route('account.addresses.default', $second))
            ->assertRedirect(route('account.addresses.index'));

        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, Address::where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_a_user_can_update_and_delete_their_address(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.addresses.store'), $this->payload);
        $address = Address::first();

        $this->actingAs($user)->put(route('account.addresses.update', $address), array_merge($this->payload, [
            'location_code' => 'JEDD5678',
        ]))->assertRedirect(route('account.addresses.index'));

        $this->assertSame('Jeddah', $address->fresh()->city);

        $this->actingAs($user)->delete(route('account.addresses.destroy', $address))
            ->assertRedirect(route('account.addresses.index'));

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_a_user_cannot_manage_another_users_address(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('account.addresses.store'), $this->payload);
        $address = Address::first();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('account.addresses.edit', $address))->assertForbidden();
        $this->actingAs($intruder)->delete(route('account.addresses.destroy', $address))->assertForbidden();
        $this->actingAs($intruder)->patch(route('account.addresses.default', $address))->assertForbidden();
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), array_merge($this->payload, [
            'phone' => 'not-a-phone',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_saved_addresses_appear_in_the_checkout_address_form(): void
    {
        // Confirms the previously-dormant read path (CheckoutController::
        // showAddressForm) now actually has data to show.
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.addresses.store'), $this->payload);

        $product = \App\Models\Product::factory()->create(['stock_quantity' => 5]);
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'qty' => 1]);

        $this->actingAs($user)->get(route('checkout.address'))
            ->assertOk()
            ->assertSee('Sara Al Qahtani');
    }

    public function test_a_user_can_add_an_address_by_pinning_a_location_instead_of_a_code(): void
    {
        $user = User::factory()->create();

        // Strings, not floats — a real browser form submission sends every
        // field as a string.
        $this->actingAs($user)->post(route('account.addresses.store'), [
            'recipient_name' => 'Sara Al Qahtani',
            'phone' => '+966500000000',
            'latitude' => '24.7136',
            'longitude' => '46.6753',
        ])->assertRedirect(route('account.addresses.index'));

        $address = Address::first();
        $this->assertNull($address->location_code);
        $this->assertNull($address->city);
        $this->assertNull($address->region);
        $this->assertNull($address->district);
        $this->assertNull($address->formatted_address);
        $this->assertNull($address->street_address);
        $this->assertNull($address->postal_code);
        $this->assertEqualsWithDelta(24.7136, $address->latitude, 0.0001);
        $this->assertEqualsWithDelta(46.6753, $address->longitude, 0.0001);
    }

    public function test_neither_a_code_nor_coordinates_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), [
            'recipient_name' => 'Sara Al Qahtani',
            'phone' => '+966500000000',
        ])->assertSessionHasErrors('location_code');

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_a_user_can_add_a_manual_full_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), [
            'recipient_name' => 'Sara Al Qahtani',
            'phone' => '+966500000000',
            'method' => Address::METHOD_MANUAL,
            'country' => 'SA',
            'city' => 'Jeddah',
            'district' => 'Al Rawdah',
            'street_address' => 'King Fahd Road',
            'building_number' => '1234',
            'apartment_number' => '5',
            'postal_code' => '23432',
            'additional_notes' => 'Near the mosque',
        ])->assertRedirect(route('account.addresses.index'));

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'method' => Address::METHOD_MANUAL,
            'city' => 'Jeddah',
            'district' => 'Al Rawdah',
            'street_address' => 'King Fahd Road',
            'building_number' => '1234',
            'apartment_number' => '5',
            'postal_code' => '23432',
            'additional_notes' => 'Near the mosque',
            'location_code' => null,
        ]);

        // formatted_address is built server-side, not trusted from the client.
        $this->assertNotNull(Address::first()->formatted_address);
    }

    public function test_a_manual_address_requires_the_building_number(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), [
            'recipient_name' => 'Sara Al Qahtani',
            'phone' => '+966500000000',
            'method' => Address::METHOD_MANUAL,
            'country' => 'SA',
            'city' => 'Jeddah',
            'district' => 'Al Rawdah',
            'street_address' => 'King Fahd Road',
        ])->assertSessionHasErrors('building_number');

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_switching_to_manual_does_not_require_a_location_code(): void
    {
        $user = User::factory()->create();

        // A stray/blank location_code left over from switching methods in
        // the UI must not be required or validated once method=manual.
        $this->actingAs($user)->post(route('account.addresses.store'), [
            'recipient_name' => 'Sara Al Qahtani',
            'phone' => '+966500000000',
            'method' => Address::METHOD_MANUAL,
            'location_code' => '',
            'country' => 'SA',
            'city' => 'Jeddah',
            'district' => 'Al Rawdah',
            'street_address' => 'King Fahd Road',
            'building_number' => '1234',
        ])->assertRedirect(route('account.addresses.index'));

        $this->assertDatabaseCount('addresses', 1);
    }
}
