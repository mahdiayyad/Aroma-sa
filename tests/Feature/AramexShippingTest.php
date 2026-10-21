<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ShipmentStatusUpdated;
use App\Models\AramexShipment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AramexShippingTest extends TestCase
{
    use RefreshDatabase;

    private const LABEL_URL = 'https://ws.dev.aramex.net/content/rpt_cache/label-440555.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.aramex' => array_merge(config('services.aramex'), [
            'base_url' => 'https://ws.dev.aramex.net/ShippingAPI.V2',
            'username' => 'api@aroma.test',
            'password' => 'secret-pass',
            'account_number' => '20016',
            'account_pin' => '331421',
            'account_entity' => 'RUH',
            'label_disk' => 'local',
            'default_item_weight_kg' => 1.0,
            'shipper' => [
                'name' => 'Aroma', 'company' => 'Aroma Gift Center', 'phone' => '+966500000000',
                'email' => 'ops@aroma.test', 'line1' => 'King Fahd Rd', 'line2' => null,
                'city' => 'Riyadh', 'post_code' => '12211', 'country_code' => 'SA',
            ],
        ])]);

        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function order(string $status = Order::STATUS_PAID, array $address = []): Order
    {
        $order = Order::factory()->create([
            'status' => $status,
            'shipping_address' => array_merge([
                'recipient_name' => 'Sara Ali',
                'phone' => '+966511111111',
                'country' => 'SA',
                'city' => 'Jeddah',
                'district' => 'Al Rawdah',
                'street_address' => 'Tahlia St',
                'building_number' => '7',
            ], $address),
        ]);

        $product = Product::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_data' => ['name' => 'Abaya'],
            'unit_price' => 100,
            'quantity' => 2,
            'line_total' => 200,
        ]);

        return $order;
    }

    private function createShipmentsResponse(string $awb = '440555', ?string $labelUrl = self::LABEL_URL): array
    {
        return [
            'HasErrors' => false,
            'Notifications' => [],
            'Shipments' => [[
                'ID' => $awb,
                'HasErrors' => false,
                'Notifications' => [],
                'ShipmentLabel' => ['LabelURL' => $labelUrl, 'LabelFileContents' => null],
            ]],
        ];
    }

    private function createShipmentsCalls(): int
    {
        return Http::recorded(function (Request $request) {
            return Str::endsWith($request->url(), '/CreateShipments');
        })->count();
    }

    public function test_admin_ships_a_paid_order_end_to_end(): void
    {
        Event::fake([ShipmentStatusUpdated::class]);
        Http::fake([
            '*/CreateShipments' => Http::response($this->createShipmentsResponse()),
            'ws.dev.aramex.net/content/*' => Http::response("%PDF-1.4\nlabel", 200),
        ]);
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.aramex.ship', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('aramex_label_ready', true)
            ->assertSessionMissing('error');

        $order->refresh();
        $shipment = $order->aramexShipment;

        $this->assertSame(Order::STATUS_SHIPPED, $order->status);
        $this->assertSame('440555', $order->tracking_number);
        $this->assertNotNull($order->shipped_at);
        $this->assertSame('440555', $shipment->awb_number);
        $this->assertSame(AramexShipment::STATUS_CREATED, $shipment->shipping_status);
        $this->assertSame(2, $shipment->pieces);
        $this->assertEquals(2.0, (float) $shipment->weight_kg);
        Storage::disk('local')->assertExists($shipment->label_path);
        $this->assertStringStartsWith('aramex-labels/', $shipment->label_path);

        Http::assertSent(function (Request $request) {
            if (! Str::endsWith($request->url(), '/CreateShipments')) {
                return false;
            }
            $consignee = $request['Shipments'][0]['Consignee'];

            return $consignee['PartyAddress']['City'] === 'Jeddah'
                && $consignee['PartyAddress']['Line1'] === 'Tahlia St'
                && $consignee['PartyAddress']['Line2'] === 'Bldg 7, Al Rawdah'
                && $consignee['Contact']['PersonName'] === 'Sara Ali';
        });

        Event::assertDispatched(ShipmentStatusUpdated::class, function (ShipmentStatusUpdated $event) use ($order) {
            return $event->order->is($order) && $event->previousStatus === null;
        });
    }

    public function test_the_order_page_shows_the_shipment_and_the_label_downloads(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response($this->createShipmentsResponse()),
            'ws.dev.aramex.net/content/*' => Http::response("%PDF-1.4\nlabel", 200),
        ]);
        $admin = $this->admin();
        $order = $this->order();

        $this->actingAs($admin)->post(route('admin.orders.aramex.ship', $order));

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('440555')
            ->assertSee(route('admin.orders.aramex.label', $order));

        $response = $this->actingAs($admin)->get(route('admin.orders.aramex.label', $order));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=aramex-label-440555.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_shipping_twice_never_creates_a_second_aramex_shipment(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response($this->createShipmentsResponse()),
            'ws.dev.aramex.net/content/*' => Http::response("%PDF-1.4\nlabel", 200),
        ]);
        $admin = $this->admin();
        $order = $this->order();

        $this->actingAs($admin)->post(route('admin.orders.aramex.ship', $order));
        $this->actingAs($admin)->post(route('admin.orders.aramex.ship', $order))
            ->assertSessionHas('status', __('admin.aramex.already_shipped', ['awb' => '440555']));

        $this->assertSame(1, $this->createShipmentsCalls());
        $this->assertSame(1, AramexShipment::count());
    }

    public function test_an_aramex_error_leaves_the_order_unshipped_and_can_be_retried(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::sequence()
                ->push([
                    'HasErrors' => true,
                    'Shipments' => [['ID' => null, 'HasErrors' => true, 'Notifications' => [['Code' => 'ERR03', 'Message' => 'Consignee phone is invalid']]]],
                ])
                ->push($this->createShipmentsResponse()),
            'ws.dev.aramex.net/content/*' => Http::response("%PDF-1.4\nlabel", 200),
        ]);
        $admin = $this->admin();
        $order = $this->order();

        $this->actingAs($admin)->post(route('admin.orders.aramex.ship', $order))
            ->assertSessionHas('error', __('admin.aramex.create_failed', ['error' => 'ERR03: Consignee phone is invalid']));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $shipment = $order->aramexShipment()->first();
        $this->assertSame(AramexShipment::STATUS_FAILED, $shipment->shipping_status);
        $this->assertNull($shipment->awb_number);

        $this->actingAs($admin)->post(route('admin.orders.aramex.ship', $order))->assertSessionMissing('error');

        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
        $this->assertSame('440555', $shipment->fresh()->awb_number);
        $this->assertSame(1, AramexShipment::count());
    }

    public function test_only_paid_or_processing_orders_can_be_shipped(): void
    {
        Http::fake();
        $admin = $this->admin();

        foreach ([Order::STATUS_PENDING, Order::STATUS_CANCELLED] as $status) {
            $order = $this->order($status);

            $this->actingAs($admin)->post(route('admin.orders.aramex.ship', $order))->assertSessionHas('error');
            $this->assertSame($status, $order->fresh()->status);
        }

        Http::assertNothingSent();
    }

    public function test_a_processing_order_ships_too(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response($this->createShipmentsResponse()),
            'ws.dev.aramex.net/content/*' => Http::response("%PDF-1.4\nlabel", 200),
        ]);
        $order = $this->order(Order::STATUS_PROCESSING);

        $this->actingAs($this->admin())->post(route('admin.orders.aramex.ship', $order));

        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
    }

    public function test_an_address_without_a_city_is_refused_before_calling_aramex(): void
    {
        Http::fake();
        $order = $this->order(Order::STATUS_PAID, ['city' => null]);

        $this->actingAs($this->admin())->post(route('admin.orders.aramex.ship', $order))
            ->assertSessionHas('error', __('admin.aramex.create_failed', ['error' => __('admin.aramex.address_no_city')]));

        Http::assertNothingSent();
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_a_label_url_on_an_unexpected_host_is_never_fetched(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response($this->createShipmentsResponse('440556', 'https://evil.example/label.pdf')),
            '*/PrintLabel' => Http::response(['HasErrors' => true, 'Notifications' => [['Code' => 'ERR', 'Message' => 'down']]]),
            '*' => Http::response('%PDF-1.4 should not be fetched', 200),
        ]);
        $order = $this->order();

        $this->actingAs($this->admin())->post(route('admin.orders.aramex.ship', $order))
            ->assertSessionHas('error', __('admin.aramex.label_failed'))
            ->assertSessionMissing('aramex_label_ready');

        // The AWB is kept even though the label failed, so a retry can't duplicate it.
        $this->assertSame('440556', $order->aramexShipment()->first()->awb_number);
        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
        Http::assertNotSent(function (Request $request) {
            return Str::contains($request->url(), 'evil.example');
        });
    }

    public function test_a_missing_label_is_fetched_again_on_download(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response($this->createShipmentsResponse('440557', null)),
            '*/PrintLabel' => Http::response(['HasErrors' => false, 'ShipmentLabel' => ['LabelURL' => self::LABEL_URL]]),
            'ws.dev.aramex.net/content/*' => Http::response("%PDF-1.4\nlabel", 200),
        ]);
        $admin = $this->admin();
        $order = $this->order();

        $this->actingAs($admin)->post(route('admin.orders.aramex.ship', $order));
        $this->assertNull($order->aramexShipment()->first()->label_path);

        $this->actingAs($admin)->get(route('admin.orders.aramex.label', $order))->assertOk();
        Storage::disk('local')->assertExists($order->aramexShipment()->first()->label_path);
    }

    public function test_customers_cannot_ship_orders(): void
    {
        Http::fake();
        $order = $this->order();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.aramex.ship', $order))
            ->assertForbidden();

        $this->assertSame(0, AramexShipment::count());
        Http::assertNothingSent();
    }

    public function test_nothing_happens_when_aramex_is_not_configured(): void
    {
        config(['services.aramex.username' => null]);
        Http::fake();
        $order = $this->order();

        $this->actingAs($this->admin())->post(route('admin.orders.aramex.ship', $order))
            ->assertSessionHas('error', __('admin.aramex.not_configured'));

        Http::assertNothingSent();
        $this->assertSame(0, AramexShipment::count());
    }

    /* Live quote ------------------------------------------------------- */

    private function cart(int $qty = 3): array
    {
        return ['cart' => ['row1' => ['product_id' => 1, 'qty' => $qty, 'unit_price' => 100.0]]];
    }

    public function test_the_live_quote_prices_the_server_side_cart(): void
    {
        Http::fake(['*/CalculateRate' => Http::response([
            'HasErrors' => false,
            'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 32.25],
        ])]);

        $this->withSession($this->cart(3))
            ->postJson(route('checkout.shipping.quote'), ['city' => 'Jeddah', 'weight_kg' => 0.01])
            ->assertOk()
            ->assertExactJson(['success' => true, 'amount' => 32.25, 'currency' => 'SAR', 'weight_kg' => 3, 'pieces' => 3]);

        Http::assertSent(function (Request $request) {
            return $request['ShipmentDetails']['ActualWeight']['Value'] == 3
                && $request['ShipmentDetails']['NumberOfPieces'] === 3
                && $request['OriginAddress']['City'] === 'Riyadh'
                && $request['DestinationAddress']['CountryCode'] === 'SA';
        });
    }

    public function test_repeated_quotes_are_cached(): void
    {
        Http::fake(['*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 20]])]);

        $this->withSession($this->cart())->postJson(route('checkout.shipping.quote'), ['city' => 'Dammam'])->assertOk();
        $this->withSession($this->cart())->postJson(route('checkout.shipping.quote'), ['city' => 'dammam'])->assertOk();

        Http::assertSentCount(1);
    }

    public function test_quote_errors_are_plain_for_shoppers(): void
    {
        Http::fake(['*/CalculateRate' => Http::response(['HasErrors' => true, 'Notifications' => [['Code' => 'ERR52', 'Message' => 'Invalid city']]])]);

        $this->withSession($this->cart())->postJson(route('checkout.shipping.quote'), ['city' => 'Nowhere'])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => __('checkout.shipping_quote.failed')]);
    }

    public function test_quote_requires_a_city_and_a_non_empty_cart(): void
    {
        Http::fake();

        $this->withSession($this->cart())->postJson(route('checkout.shipping.quote'), [])->assertStatus(422)->assertJsonValidationErrors('city');
        $this->flushSession();
        $this->postJson(route('checkout.shipping.quote'), ['city' => 'Jeddah'])
            ->assertStatus(422)
            ->assertJson(['message' => __('checkout.shipping_quote.empty_cart')]);

        Http::assertNothingSent();
    }

    public function test_quote_reports_unavailable_when_not_configured(): void
    {
        config(['services.aramex.password' => null]);

        $this->withSession($this->cart())->postJson(route('checkout.shipping.quote'), ['city' => 'Jeddah'])->assertStatus(503);
    }
}
