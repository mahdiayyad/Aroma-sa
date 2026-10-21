<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AramexShipment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Demo National Address data must never reach real shoppers or shipments.
 */
class NationalAddressDemoTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO = 'Al Olaya, Riyadh, Riyadh Region (ABCD1234)';

    private function lookupUnavailable(): void
    {
        config([
            'services.national_address.base_url' => '',
            'services.national_address.api_key' => '',
            'services.national_address.demo' => false,
        ]);
    }

    public function test_the_address_form_offers_only_full_address_when_no_lookup_is_available(): void
    {
        $this->lookupUnavailable();

        $this->actingAs(User::factory()->create())
            ->get(route('account.addresses.create'))
            ->assertOk()
            ->assertDontSee('js-location-code', false)
            ->assertDontSee('value="'.Address::METHOD_NATIONAL_CODE.'"', false)
            ->assertSee('<input type="hidden" name="method" value="'.Address::METHOD_MANUAL.'">', false);
    }

    public function test_the_address_form_offers_both_methods_in_demo_mode(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '', 'services.national_address.demo' => true]);

        $this->actingAs(User::factory()->create())
            ->get(route('account.addresses.create'))
            ->assertOk()
            ->assertSee('js-location-code', false)
            ->assertSee('value="'.Address::METHOD_NATIONAL_CODE.'"', false);
    }

    public function test_aramex_refuses_to_ship_to_a_demo_address(): void
    {
        config(['services.aramex' => array_merge(config('services.aramex'), [
            'username' => 'u', 'password' => 'p', 'account_number' => '1', 'account_pin' => '1', 'account_entity' => 'DHA',
        ])]);
        Http::fake();

        $address = ['recipient_name' => 'Sara', 'phone' => '966511111111', 'method' => 'national_code', 'location_code' => 'ABCD1234',
            'city' => 'Riyadh', 'district' => 'Al Olaya', 'country' => 'SA', 'formatted_address' => self::DEMO];
        $order = Order::factory()->create(['status' => Order::STATUS_PAID, 'shipping_address' => $address]);
        $order->items()->create(['product_id' => Product::factory()->create()->id, 'product_data' => ['name' => 'Abaya'],
            'unit_price' => 100, 'quantity' => 1, 'line_total' => 100]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->post(route('admin.orders.aramex.ship', $order))
            ->assertSessionHas('error', __('admin.aramex.create_failed', ['error' => __('admin.aramex.address_demo')]));

        Http::assertNothingSent();
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertNull(AramexShipment::where('order_id', $order->id)->value('awb_number'));
    }

    public function test_the_admin_order_page_flags_a_demo_address_even_without_the_flag(): void
    {
        $order = Order::factory()->create(['shipping_address' => [
            'recipient_name' => 'Sara', 'phone' => '966511111111', 'method' => 'national_code', 'location_code' => 'ABCD1234',
            'city' => 'Riyadh', 'formatted_address' => self::DEMO,
        ]]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(__('location.preview.demo_badge'));
    }

    public function test_the_report_lists_demo_addresses_and_orders_only(): void
    {
        $user = User::factory()->create(['name' => 'Demo Customer']);
        $user->addresses()->create(['type' => 'shipping', 'recipient_name' => 'Demo', 'phone' => '966511111111',
            'city' => 'Riyadh', 'region' => 'Riyadh Region', 'street_address' => '', 'location_code' => 'ABCD1234',
            'formatted_address' => self::DEMO]);
        $user->addresses()->create(['type' => 'shipping', 'recipient_name' => 'Real', 'phone' => '966511111112',
            'city' => 'Jeddah', 'region' => 'Makkah', 'street_address' => 'Tahlia St',
            'formatted_address' => 'Tahlia St, Bldg 7, Al Rawdah, Jeddah']);

        Order::factory()->create(['order_number' => 'AR-DEMO-1', 'shipping_address' => ['city' => 'Riyadh', 'formatted_address' => self::DEMO]]);
        Order::factory()->create(['order_number' => 'AR-FLAG-1', 'shipping_address' => ['city' => 'Riyadh', 'is_stub' => true]]);
        Order::factory()->create(['order_number' => 'AR-REAL-1']);

        $this->artisan('national-address:demo-data')
            ->expectsOutput('Saved addresses with demo data: 1')
            ->expectsOutput('Orders with demo address data: 2')
            ->assertExitCode(0);
    }
}
