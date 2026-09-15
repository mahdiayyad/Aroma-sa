<?php

namespace Tests\Feature\Admin;

use App\Models\GiftCard;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin order-show Blade page (not the JSON API covered by
 * AdminOrderTest) — specifically the gift + delivery cards added so an
 * admin can manually fulfil an order without any carrier integration.
 */
class AdminOrderShowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private array $address = [
        'recipient_name' => 'Sara', 'phone' => '0500000000',
        'street_address' => 'King Fahd Rd', 'city' => 'Riyadh', 'region' => 'Riyadh', 'postal_code' => '',
    ];

    public function test_the_order_page_renders_for_a_plain_non_gift_order(): void
    {
        $order = Order::create([
            'order_number' => 'AR-2026-000100', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 'sara@example.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 200, 'total_amount' => 200,
        ]);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(__('admin.orders.not_a_gift'))
            ->assertSee(__('admin.orders.no_delivery'));
    }

    public function test_the_order_page_renders_gift_and_delivery_details(): void
    {
        $card = GiftCard::create([
            'name' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
            'slug' => 'birthday', 'image' => 'gift-cards/birthday.jpg', 'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'AR-2026-000101', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 'sara@example.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 200, 'total_amount' => 215,

            'is_gift' => true,
            'gift_message' => 'Happy birthday, enjoy!',
            'is_anonymous' => false,
            'gift_wrap_fee' => 15,
            'greeting_card_id' => $card->id,
            'gift_card_to' => 'Layla',
            'gift_card_from' => 'Sara',

            'delivery_date' => now()->addDays(3)->toDateString(),
            'delivery_time_slot' => Order::DELIVERY_SLOT_EVENING,
            'delivery_instructions' => 'Leave with the doorman',
        ]);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Happy birthday, enjoy!')
            ->assertSee('Layla')
            ->assertSee('Sara')
            ->assertSee(__('delivery.slots.evening'))
            ->assertSee('Leave with the doorman')
            ->assertDontSee(__('admin.orders.anonymous'));
    }

    public function test_the_order_page_shows_a_manual_address_and_its_method_badge(): void
    {
        $manualAddress = [
            'method' => \App\Models\Address::METHOD_MANUAL,
            'recipient_name' => 'Sara', 'phone' => '0500000000',
            'country' => 'SA', 'city' => 'Jeddah', 'district' => 'Al Rawdah',
            'street_address' => 'King Fahd Road', 'building_number' => '1234',
            'postal_code' => '23432',
        ];

        $order = Order::create([
            'order_number' => 'AR-2026-000103', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 'sara@example.com', 'customer_phone' => '0500000000',
            'billing_address' => $manualAddress, 'shipping_address' => $manualAddress,
            'subtotal' => 200, 'total_amount' => 200,
        ]);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(__('location.method.manual'))
            ->assertSee('King Fahd Road')
            ->assertSee('1234');
    }

    public function test_an_anonymous_gift_hides_the_from_name_and_signature(): void
    {
        $order = Order::create([
            'order_number' => 'AR-2026-000102', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 'sara@example.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 200, 'total_amount' => 200,

            'is_gift' => true,
            'is_anonymous' => true,
            'gift_card_from' => 'Should not appear',
        ]);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(__('admin.orders.anonymous'))
            ->assertDontSee('Should not appear');
    }
}
