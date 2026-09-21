<?php

namespace Tests\Unit;

use App\Events\OrderPaid;
use App\Http\Controllers\Webhooks\TamaraWebhookController;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderStatusService;
use App\Services\Payment\TamaraOrderOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tamara's post-payment lifecycle: webhook reconciliation (JWT auth, both
 * payload shapes, captured / refunded), capture on shipment, cancel vs. refund
 * on cancellation, and manual partial refunds.
 */
class TamaraOperationsTest extends TestCase
{
    use RefreshDatabase;

    private const TAMARA_ID = 'tam-uuid-1';

    private array $address = [
        'recipient_name' => 'Sara', 'phone' => '0500000000', 'street_address' => 'K',
        'city' => 'Riyadh', 'region' => 'Riyadh', 'postal_code' => '',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tamara.api_token'          => 'tok',
            'services.tamara.notification_token' => 'whsec',
            'services.tamara.base_url'           => 'https://api-sandbox.tamara.co',
            'services.tamara.shipping_company'   => 'Aroma Delivery',
        ]);
    }

    /** @return array{0:Order,1:Payment} */
    private function tamaraOrder(string $orderStatus, string $paymentStatus, ?string $reference = self::TAMARA_ID): array
    {
        static $n = 0;
        $n++;

        $order = Order::create([
            'order_number' => 'AR-2026-0009'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'status' => $orderStatus,
            'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 100, 'total_amount' => 100,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => 'tamara', 'method' => 'tamara',
            'status' => $paymentStatus, 'amount' => 100, 'currency' => 'SAR',
            'reference_number' => $reference,
        ]);

        return [$order, $payment];
    }

    private function jwt(string $secret = 'whsec'): string
    {
        $b64 = function ($v) { return rtrim(strtr(base64_encode($v), '+/', '-_'), '='); };
        $h = $b64(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $p = $b64(json_encode(['iss' => 'Tamara', 'exp' => time() + 300]));

        return $h.'.'.$p.'.'.$b64(hash_hmac('sha256', $h.'.'.$p, $secret, true));
    }

    private function webhook(array $payload, ?string $token = null): \Illuminate\Http\Response
    {
        $request = Request::create('/webhooks/tamara?tamaraToken='.($token ?? $this->jwt()), 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');

        return app(TamaraWebhookController::class)->handle($request);
    }

    private function fakeOrderStatus(string $status): void
    {
        Http::fake(['*tamara.co/*' => Http::response(['order_id' => self::TAMARA_ID, 'status' => $status], 200)]);
    }

    /* Webhook ------------------------------------------------------------ */

    public function test_a_jwt_signed_legacy_order_status_notification_marks_the_order_paid_as_authorized(): void
    {
        Event::fake([OrderPaid::class]);
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PENDING, Payment::STATUS_PENDING, null);
        $this->fakeOrderStatus('authorised');

        // Legacy IPN shape: order_status, no event_type.
        $response = $this->webhook([
            'order_id' => self::TAMARA_ID, 'order_reference_id' => $order->order_number, 'order_status' => 'approved',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_AUTHORIZED, $payment->fresh()->status, 'authorised is not captured');
        $this->assertSame(self::TAMARA_ID, $payment->fresh()->reference_number, 'Tamara id back-filled');
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_an_unsigned_or_wrongly_signed_notification_is_rejected(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PENDING, Payment::STATUS_PENDING);
        $this->fakeOrderStatus('authorised');

        $response = $this->webhook(['order_reference_id' => $order->order_number, 'event_type' => 'order_approved'], $this->jwt('wrong'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_an_auto_authorised_order_that_is_already_captured_is_recorded_as_captured(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PENDING, Payment::STATUS_PENDING);
        $this->fakeOrderStatus('fully_captured');

        $this->webhook(['order_id' => self::TAMARA_ID, 'order_reference_id' => $order->order_number, 'event_type' => 'order_approved']);

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
    }

    public function test_a_captured_notification_catches_the_payment_up_without_touching_the_order_status(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_SHIPPED, Payment::STATUS_AUTHORIZED);
        $this->fakeOrderStatus('fully_captured');

        $this->webhook(['order_id' => self::TAMARA_ID, 'order_reference_id' => $order->order_number, 'event_type' => 'order_captured']);

        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status, 'a late webhook must not rewind fulfilment');
    }

    public function test_a_refunded_notification_records_the_refund(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_DELIVERED, Payment::STATUS_CAPTURED);
        $this->fakeOrderStatus('fully_refunded');

        $this->webhook(['order_id' => self::TAMARA_ID, 'order_reference_id' => $order->order_number, 'event_type' => 'order_refunded']);

        $fresh = $payment->fresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $fresh->status);
        $this->assertEquals(100, $fresh->refunded_amount);
    }

    public function test_a_declined_notification_fails_an_unpaid_order_but_never_a_paid_one(): void
    {
        [$unpaid, $unpaidPayment] = $this->tamaraOrder(Order::STATUS_PENDING, Payment::STATUS_PENDING);
        $this->webhook(['order_reference_id' => $unpaid->order_number, 'event_type' => 'order_declined']);
        $this->assertSame(Payment::STATUS_FAILED, $unpaidPayment->fresh()->status);

        [$paid, $paidPayment] = $this->tamaraOrder(Order::STATUS_PAID, Payment::STATUS_AUTHORIZED, 'tam-other');
        $this->webhook(['order_reference_id' => $paid->order_number, 'order_status' => 'canceled']);
        $this->assertSame(Payment::STATUS_AUTHORIZED, $paidPayment->fresh()->status);
        $this->assertSame(Order::STATUS_PAID, $paid->fresh()->status);
    }

    public function test_a_notification_for_an_unknown_order_is_acknowledged(): void
    {
        $this->assertSame(200, $this->webhook(['order_reference_id' => 'NOPE', 'event_type' => 'order_approved'])->getStatusCode());
    }

    /* Capture on shipment ------------------------------------------------- */

    public function test_shipping_an_authorised_tamara_order_captures_it(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PROCESSING, Payment::STATUS_AUTHORIZED);
        Http::fake([
            '*tamara.co/payments/capture' => Http::response(['capture_id' => 'cap-1', 'status' => 'fully_captured'], 200),
            '*tamara.co/orders/'.self::TAMARA_ID => Http::response(['order_id' => self::TAMARA_ID, 'status' => 'authorised'], 200),
        ]);

        $service = app(OrderStatusService::class);
        $service->transition($order, Order::STATUS_SHIPPED, ['tracking_number' => 'TRK-77']);

        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
        $this->assertTrue($service->notes()[0]['success']);

        Http::assertSent(function ($request) {
            if (substr($request->url(), -strlen('/payments/capture')) !== '/payments/capture') {
                return false;
            }
            $b = $request->data();

            return $b['order_id'] === self::TAMARA_ID
                && $b['total_amount'] === ['amount' => '100.00', 'currency' => 'SAR']
                && $b['shipping_info']['shipping_company'] === 'Aroma Delivery'
                && $b['shipping_info']['tracking_number'] === 'TRK-77'
                && ! empty($b['shipping_info']['shipped_at']);
        });
    }

    public function test_shipping_skips_the_capture_when_tamara_already_captured(): void
    {
        // Auto-authorisation / auto-capture: Tamara reports fully_captured already.
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PROCESSING, Payment::STATUS_AUTHORIZED);
        $this->fakeOrderStatus('fully_captured');

        app(OrderStatusService::class)->transition($order, Order::STATUS_SHIPPED);

        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
        Http::assertNotSent(function ($request) {
            return substr($request->url(), -strlen('/payments/capture')) === '/payments/capture';
        });
    }

    public function test_a_failed_capture_never_blocks_shipping_and_can_be_retried(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PROCESSING, Payment::STATUS_AUTHORIZED);
        // First capture attempt fails (503), the retry succeeds.
        Http::fake([
            '*tamara.co/payments/capture' => Http::sequence()
                ->push(['message' => 'unavailable'], 503)
                ->push(['capture_id' => 'cap-2', 'status' => 'fully_captured'], 200),
            '*tamara.co/orders/'.self::TAMARA_ID => Http::response(['status' => 'authorised'], 200),
        ]);

        $service = app(OrderStatusService::class);
        $service->transition($order, Order::STATUS_SHIPPED);

        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_AUTHORIZED, $payment->fresh()->status);
        $this->assertFalse($service->notes()[0]['success']);

        $retry = app(TamaraOrderOperations::class)->capture($order->fresh());
        $this->assertTrue($retry['success']);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
    }

    public function test_orders_without_a_paid_tamara_payment_are_left_alone(): void
    {
        $order = Order::create([
            'order_number' => 'AR-2026-000999', 'status' => Order::STATUS_PROCESSING,
            'customer_name' => 'S', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 50, 'total_amount' => 50,
        ]);
        Payment::create(['order_id' => $order->id, 'gateway' => 'moyasar', 'method' => 'mada', 'status' => Payment::STATUS_CAPTURED, 'amount' => 50, 'currency' => 'SAR']);
        Http::fake();

        $service = app(OrderStatusService::class);
        $service->transition($order, Order::STATUS_SHIPPED);

        $this->assertSame([], $service->notes());
        Http::assertNothingSent();
    }

    /* Cancel / refund ----------------------------------------------------- */

    public function test_cancelling_an_authorised_order_releases_the_hold(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PAID, Payment::STATUS_AUTHORIZED);
        Http::fake([
            '*tamara.co/orders/'.self::TAMARA_ID.'/cancel' => Http::response(['status' => 'canceled', 'cancel_id' => 'c1'], 200),
            '*tamara.co/orders/'.self::TAMARA_ID => Http::response(['status' => 'authorised'], 200),
        ]);

        app(OrderStatusService::class)->transition($order, Order::STATUS_CANCELLED);

        $this->assertSame(Payment::STATUS_CANCELLED, $payment->fresh()->status);
        Http::assertNotSent(function ($request) {
            return strpos($request->url(), 'simplified-refund') !== false;
        });
    }

    public function test_cancelling_a_captured_order_refunds_it_in_full_through_tamara(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_PROCESSING, Payment::STATUS_CAPTURED);
        Http::fake([
            '*tamara.co/payments/simplified-refund/'.self::TAMARA_ID => Http::response([
                'status' => 'fully_refunded', 'refund_id' => 'r1', 'refunded_amount' => ['amount' => 100, 'currency' => 'SAR'],
            ], 200),
        ]);

        $service = app(OrderStatusService::class);
        $service->transition($order, Order::STATUS_CANCELLED);

        $fresh = $payment->fresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $fresh->status);
        $this->assertEquals(100, $fresh->refunded_amount);
        $this->assertTrue($service->notes()[0]['success']);
        Http::assertSent(function ($request) {
            return strpos($request->url(), 'simplified-refund') !== false
                && $request->data()['total_amount'] === ['amount' => '100.00', 'currency' => 'SAR']
                && ! empty($request->data()['comment']);
        });
    }

    public function test_partial_refunds_accumulate_and_the_payment_flips_to_refunded_when_complete(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_DELIVERED, Payment::STATUS_CAPTURED);
        $ops = app(TamaraOrderOperations::class);

        Http::fake(['*tamara.co/payments/simplified-refund/*' => Http::sequence()
            ->push(['status' => 'partially_refunded', 'refund_id' => 'r1', 'refunded_amount' => ['amount' => 40, 'currency' => 'SAR']], 200)
            ->push(['status' => 'fully_refunded', 'refund_id' => 'r2', 'refunded_amount' => ['amount' => 60, 'currency' => 'SAR']], 200),
        ]);

        $this->assertTrue($ops->refund($order, 40.0, 'damaged item')['success']);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
        $this->assertEquals(40, $payment->fresh()->refunded_amount);

        $this->assertTrue($ops->refund($order, 60.0, 'rest')['success']);
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertEquals(100, $payment->fresh()->refunded_amount);
    }

    public function test_a_refund_beyond_what_is_left_or_on_an_uncaptured_payment_is_refused_without_calling_tamara(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_DELIVERED, Payment::STATUS_CAPTURED);
        Http::fake();
        $ops = app(TamaraOrderOperations::class);

        $this->assertFalse($ops->refund($order, 100.01, 'too much')['success']);
        $this->assertFalse($ops->refund($order, 0.0, 'nothing')['success']);

        $payment->update(['status' => Payment::STATUS_AUTHORIZED]);
        $this->assertFalse($ops->refund($order, 10.0, 'not captured')['success']);

        Http::assertNothingSent();
    }

    public function test_a_failed_refund_leaves_the_payment_untouched(): void
    {
        [$order, $payment] = $this->tamaraOrder(Order::STATUS_DELIVERED, Payment::STATUS_CAPTURED);
        Http::fake(['*tamara.co/payments/simplified-refund/*' => Http::response(['message' => 'not allowed'], 409)]);

        $result = app(TamaraOrderOperations::class)->refund($order, 20.0, 'x');

        $this->assertFalse($result['success']);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
        $this->assertEquals(0, $payment->fresh()->refunded_amount);
    }
}
