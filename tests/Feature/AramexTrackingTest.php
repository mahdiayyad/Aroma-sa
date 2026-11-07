<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ShipmentStatusUpdated;
use App\Models\AramexShipment;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AramexTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'aramex-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.aramex' => array_merge(config('services.aramex'), [
            'username' => 'api@aroma.test',
            'password' => 'secret-pass',
            'account_number' => '20016',
            'account_pin' => '331421',
            'account_entity' => 'RUH',
            'webhook_secret' => self::SECRET,
        ])]);

        Event::fake([ShipmentStatusUpdated::class]);
    }

    private function shipment(string $awb = '440777', string $orderStatus = Order::STATUS_SHIPPED): AramexShipment
    {
        $order = Order::factory()->create(['status' => $orderStatus, 'tracking_number' => $awb]);

        return AramexShipment::create([
            'order_id' => $order->id,
            'awb_number' => $awb,
            'shipping_status' => AramexShipment::STATUS_CREATED,
        ]);
    }

    private function update(string $code, string $description, string $at, string $awb = '440777'): array
    {
        return [
            'WaybillNumber' => $awb,
            'UpdateCode' => $code,
            'UpdateDescription' => $description,
            'UpdateDateTime' => $at,
            'UpdateLocation' => 'Jeddah',
        ];
    }

    private function push(array $payload, string $token = self::SECRET)
    {
        return $this->postJson(route('shipping.webhook.aramex'), $payload, ['X-Aramex-Token' => $token]);
    }

    public function test_requests_without_the_shared_secret_are_rejected(): void
    {
        $shipment = $this->shipment();
        $payload = $this->update('SH005', 'Delivered', '2026-10-20T10:00:00Z');

        $this->push($payload, 'wrong-secret')->assertStatus(401);
        $this->postJson(route('shipping.webhook.aramex'), $payload)->assertStatus(401);

        $this->assertSame(AramexShipment::STATUS_CREATED, $shipment->fresh()->shipping_status);
        Event::assertNotDispatched(ShipmentStatusUpdated::class);
    }

    public function test_every_request_is_rejected_when_no_secret_is_configured(): void
    {
        config(['services.aramex.webhook_secret' => null]);
        $this->shipment();

        $this->push($this->update('SH005', 'Delivered', '2026-10-20T10:00:00Z'), '')->assertStatus(401);
    }

    public function test_a_query_string_token_is_accepted(): void
    {
        $this->shipment();

        $this->postJson(route('shipping.webhook.aramex', ['token' => self::SECRET]), $this->update('SH003', 'Out for Delivery', '2026-10-20T08:00:00Z'))
            ->assertOk();
    }

    public function test_delivered_marks_the_shipment_and_the_order_delivered(): void
    {
        $shipment = $this->shipment();

        $this->push($this->update('SH005', 'Delivered', '2026-10-20T10:00:00Z'))
            ->assertOk()
            ->assertJson(['received' => 1, 'updated' => 1]);

        $shipment->refresh();
        $order = $shipment->order;
        $this->assertSame(AramexShipment::STATUS_DELIVERED, $shipment->shipping_status);
        $this->assertSame('SH005', $shipment->status_code);
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);

        Event::assertDispatched(ShipmentStatusUpdated::class, function (ShipmentStatusUpdated $event) use ($order) {
            return $event->order->is($order)
                && $event->previousStatus === AramexShipment::STATUS_CREATED
                && $event->shipment->shipping_status === AramexShipment::STATUS_DELIVERED;
        });
    }

    public function test_out_for_delivery_is_recognised_from_the_description(): void
    {
        $shipment = $this->shipment();

        $this->push($this->update('SH999', 'Out for Delivery', '2026-10-20T08:00:00Z'))->assertOk();

        $this->assertSame(AramexShipment::STATUS_OUT_FOR_DELIVERY, $shipment->fresh()->shipping_status);
        $this->assertSame(Order::STATUS_SHIPPED, $shipment->order->fresh()->status);
        Event::assertDispatchedTimes(ShipmentStatusUpdated::class, 1);
    }

    public function test_a_redelivered_update_is_ignored(): void
    {
        $shipment = $this->shipment();
        $payload = $this->update('SH005', 'Delivered', '2026-10-20T10:00:00Z');

        $this->push($payload)->assertOk();
        $this->push($payload)->assertOk()->assertJson(['updated' => 0]);

        Event::assertDispatchedTimes(ShipmentStatusUpdated::class, 1);
        $this->assertCount(1, $shipment->fresh()->history);
    }

    public function test_an_older_update_arriving_late_does_not_move_the_status_back(): void
    {
        $shipment = $this->shipment();

        $this->push($this->update('SH003', 'Out for Delivery', '2026-10-20T10:00:00Z'))->assertOk();
        $this->push($this->update('SH047', 'Shipment in transit', '2026-10-19T08:00:00Z'))->assertOk();

        $shipment->refresh();
        $this->assertSame(AramexShipment::STATUS_OUT_FOR_DELIVERY, $shipment->shipping_status);
        $this->assertCount(2, $shipment->history);
        $this->assertSame('Out for Delivery', $shipment->timeline()[0]['description']);
    }

    public function test_delivered_is_final(): void
    {
        $shipment = $this->shipment();

        $this->push($this->update('SH005', 'Delivered', '2026-10-20T10:00:00Z'))->assertOk();
        $this->push($this->update('SH069', 'Returned to Shipper', '2026-10-21T10:00:00Z'))->assertOk();

        $this->assertSame(AramexShipment::STATUS_DELIVERED, $shipment->fresh()->shipping_status);
        Event::assertDispatchedTimes(ShipmentStatusUpdated::class, 1);
    }

    public function test_a_failed_attempt_is_flagged_without_touching_the_order(): void
    {
        $shipment = $this->shipment();

        $this->push($this->update('SH033', 'Delivery attempted - consignee not available', '2026-10-20T12:00:00Z'))->assertOk();

        $this->assertSame(AramexShipment::STATUS_EXCEPTION, $shipment->fresh()->shipping_status);
        $this->assertSame(Order::STATUS_SHIPPED, $shipment->order->fresh()->status);
    }

    /** The exact shape and header from Aramex's WebHook API specification. */
    public function test_the_documented_aramex_payload_with_an_api_key_header_is_applied(): void
    {
        $shipment = $this->shipment('46285244865');

        $this->postJson(route('shipping.webhook.aramex'), [
            'Key' => '46285244865',
            'Value' => [
                'UpdateCode' => 'SH005',
                'WaybillNumber' => '46285244865',
                'UpdateDateTime' => '2020-10-11T09:58:00Z',
                'Comments' => 'Delivered to  Consignee',
                'UpdateLocation' => 'Jeddah, Saudi Arabia',
                'ProblemCode' => '',
                'OrderNumber' => '',
            ],
        ], ['x-api-key' => self::SECRET])
            ->assertOk()
            ->assertJson(['received' => 1, 'updated' => 1]);

        $shipment->refresh();
        $this->assertSame(AramexShipment::STATUS_DELIVERED, $shipment->shipping_status);
        $this->assertSame(Order::STATUS_DELIVERED, $shipment->order->status);
        // No description is pushed, so Aramex's own one for the code is used.
        $this->assertSame('Delivered', $shipment->status_description);
        $this->assertSame('Delivered to  Consignee', $shipment->history[0]['comment']);
        $this->assertSame('Jeddah, Saudi Arabia', $shipment->history[0]['location']);
    }

    public function test_a_wrong_api_key_is_rejected(): void
    {
        $shipment = $this->shipment('46285244865');

        $this->postJson(route('shipping.webhook.aramex'), [
            'Key' => '46285244865',
            'Value' => ['UpdateCode' => 'SH005', 'WaybillNumber' => '46285244865', 'UpdateDateTime' => '2020-10-11T09:58:00Z'],
        ], ['x-api-key' => 'not-the-secret'])->assertStatus(401);

        $this->assertSame(AramexShipment::STATUS_CREATED, $shipment->fresh()->shipping_status);
    }

    public function test_the_waybill_falls_back_to_the_key_when_the_update_omits_it(): void
    {
        $shipment = $this->shipment('46285244865');

        $this->postJson(route('shipping.webhook.aramex'), [
            'Key' => '46285244865',
            'Value' => ['UpdateCode' => 'SH003', 'UpdateDateTime' => '2026-10-20T08:00:00Z'],
        ], ['x-api-key' => self::SECRET])->assertOk();

        $this->assertSame(AramexShipment::STATUS_OUT_FOR_DELIVERY, $shipment->fresh()->shipping_status);
    }

    public function test_an_informational_update_keeps_the_current_status(): void
    {
        $shipment = $this->shipment();

        $this->push($this->update('SH003', 'Out for Delivery', '2026-10-20T08:00:00Z'))->assertOk();
        $this->push($this->update('SH271', 'SMS Sent to Consignee', '2026-10-20T08:05:00Z'))->assertOk();

        $shipment->refresh();
        $this->assertSame(AramexShipment::STATUS_OUT_FOR_DELIVERY, $shipment->shipping_status);
        $this->assertSame('SH271', $shipment->status_code);
    }

    public function test_official_codes_classify_without_a_description(): void
    {
        $service = app(\App\Services\AramexFulfilmentService::class);

        $this->assertSame(AramexShipment::STATUS_DELIVERED, $service->classify('SH597', ''));
        $this->assertSame(AramexShipment::STATUS_RETURNED, $service->classify('SH407', ''));
        $this->assertSame(AramexShipment::STATUS_EXCEPTION, $service->classify('SH294', ''));
        $this->assertSame(AramexShipment::STATUS_IN_TRANSIT, $service->classify('SH022', ''));
        // Ambiguous in Aramex's sheet ("Picked Up From Shipper" / "Collected by
        // Consignee"), so never treated as delivered.
        $this->assertNotSame(AramexShipment::STATUS_DELIVERED, $service->classify('SH006', 'Picked Up From Shipper'));
    }

    public function test_the_tracking_results_shape_and_wcf_dates_are_understood(): void
    {
        $shipment = $this->shipment();

        $this->push([
            'TrackingResults' => [[
                'Key' => '440777',
                'Value' => [
                    ['UpdateCode' => 'SH001', 'UpdateDescription' => 'Record created', 'UpdateDateTime' => '/Date(1760947200000+0300)/'],
                    ['UpdateCode' => 'SH005', 'UpdateDescription' => 'Delivered', 'UpdateDateTime' => '/Date(1760958000000+0300)/'],
                ],
            ]],
        ])->assertOk()->assertJson(['received' => 1, 'updated' => 1]);

        $shipment->refresh();
        $this->assertSame(AramexShipment::STATUS_DELIVERED, $shipment->shipping_status);
        $this->assertSame('2025-10-20 11:00:00', $shipment->last_event_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_list_of_updates_for_several_shipments_is_applied(): void
    {
        $first = $this->shipment('440801');
        $second = $this->shipment('440802');

        $this->push([
            $this->update('SH003', 'Out for Delivery', '2026-10-20T08:00:00Z', '440801'),
            $this->update('SH005', 'Delivered', '2026-10-20T09:00:00Z', '440802'),
        ])->assertOk()->assertJson(['received' => 2, 'updated' => 2]);

        $this->assertSame(AramexShipment::STATUS_OUT_FOR_DELIVERY, $first->fresh()->shipping_status);
        $this->assertSame(AramexShipment::STATUS_DELIVERED, $second->fresh()->shipping_status);
    }

    public function test_an_unknown_waybill_is_acknowledged(): void
    {
        $this->push($this->update('SH005', 'Delivered', '2026-10-20T10:00:00Z', '999999'))
            ->assertOk()
            ->assertJson(['received' => 1, 'updated' => 0]);

        Event::assertNotDispatched(ShipmentStatusUpdated::class);
    }

    public function test_the_tracking_command_pulls_updates_for_open_shipments(): void
    {
        $open = $this->shipment('440901');
        $delivered = $this->shipment('440902', Order::STATUS_DELIVERED);
        $delivered->update(['shipping_status' => AramexShipment::STATUS_DELIVERED]);

        Http::fake(['*/TrackShipments' => Http::response([
            'HasErrors' => false,
            'TrackingResults' => [[
                'Key' => '440901',
                'Value' => [['WaybillNumber' => '440901', 'UpdateCode' => 'SH005', 'UpdateDescription' => 'Delivered', 'UpdateDateTime' => '/Date(1760958000000)/']],
            ]],
        ])]);

        $this->artisan('aramex:track')
            ->expectsOutput('Checked 1 shipment(s); 1 changed status.')
            ->assertExitCode(0);

        $this->assertSame(AramexShipment::STATUS_DELIVERED, $open->fresh()->shipping_status);
        $this->assertSame(Order::STATUS_DELIVERED, $open->order->fresh()->status);
        Http::assertSent(function ($request) {
            return $request['Shipments'] === ['440901'];
        });
    }
}
