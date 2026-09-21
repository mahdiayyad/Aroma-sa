<?php

namespace Tests\Unit;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Http\Controllers\Webhooks\TamaraWebhookController;
use App\Services\Payment\MoyasarPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for the checkout-refactor analysis finding: OrderPaid
 * previously only fired from the Moyasar webhook, never from the customer
 * -return callback — meaning most orders (and all Tabby/Tamara ones) never
 * triggered a confirmation email. Fixed by centralising the paid-transition
 * side effects in CheckoutService::markOrderAsPaid.
 */
class OrderPaidEventTest extends TestCase
{
    use RefreshDatabase;

    private array $address = [
        'recipient_name' => 'Sara', 'phone' => '0500000000', 'street_address' => 'K',
        'city' => 'Riyadh', 'region' => 'Riyadh', 'postal_code' => '',
    ];

    public function test_marking_an_order_paid_dispatches_order_paid_exactly_once(): void
    {
        Event::fake([OrderPaid::class]);

        $cart = new CartService();
        $checkout = app()->makeWith(CheckoutService::class, ['cart' => $cart]);
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 5]);
        $cart->add($product->id, null, 1);

        $order = $checkout->createOrder(null, ['billing_address' => $this->address, 'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000']);
        $payment = $checkout->createPayment($order, 'moyasar', 'mada');

        $checkout->markOrderAsPaid($order, $payment);

        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_marking_an_already_paid_order_does_not_dispatch_again(): void
    {
        // Simulates the real race this fix targets: the webhook and the
        // customer-return callback both observing "gateway says paid".
        Event::fake([OrderPaid::class]);

        $cart = new CartService();
        $checkout = app()->makeWith(CheckoutService::class, ['cart' => $cart]);
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 5]);
        $cart->add($product->id, null, 1);

        $order = $checkout->createOrder(null, ['billing_address' => $this->address, 'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000']);
        $payment = $checkout->createPayment($order, 'moyasar', 'mada');

        $checkout->markOrderAsPaid($order, $payment); // webhook lands first
        $checkout->markOrderAsPaid($order, $payment); // callback lands second

        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_the_moyasar_webhook_path_now_dispatches_order_paid_too(): void
    {
        // Previously handlePaymentSuccess() updated status inline and never
        // went through markOrderAsPaid() from this angle either — verify the
        // webhook's own success path fires the event via the shared method.
        Event::fake([OrderPaid::class]);
        config(['services.moyasar.webhook_secret' => 'whsec']);

        $order = Order::create([
            'order_number' => 'AR-2026-000777', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 100, 'total_amount' => 100,
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'moyasar', 'method' => 'mada',
            'status' => Payment::STATUS_PENDING, 'amount' => 100, 'currency' => 'SAR',
        ]);

        app(MoyasarPaymentService::class)->handleWebhook([
            'event' => 'invoice.paid',
            'data' => [
                'id' => 'pay_1', 'amount' => 10000, 'currency' => 'SAR', 'method' => 'mada',
                'metadata' => ['order_id' => $order->id],
            ],
        ]);

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_the_tamara_webhook_authorises_and_dispatches_order_paid(): void
    {
        Event::fake([OrderPaid::class]);
        config(['services.tamara.api_token' => 'tok', 'services.tamara.notification_token' => 'whsec']);
        Http::fake(['*tamara.co/*' => Http::response(['order_id' => 'tam_1', 'status' => 'approved'], 200)]);

        $order = Order::create([
            'order_number' => 'AR-2026-000778', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 100, 'total_amount' => 100,
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'tamara', 'method' => 'tamara',
            'status' => Payment::STATUS_PENDING, 'amount' => 100, 'currency' => 'SAR',
        ]);

        $request = Request::create('/webhooks/tamara', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer whsec',
        ], json_encode([
            'event_type' => 'order_approved',
            'order_id' => 'tam_1',
            'order_reference_id' => $order->order_number,
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = app(TamaraWebhookController::class)->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_the_tamara_webhook_rejects_a_wrong_notification_token(): void
    {
        Event::fake([OrderPaid::class]);
        config(['services.tamara.api_token' => 'tok', 'services.tamara.notification_token' => 'whsec']);

        $order = Order::create([
            'order_number' => 'AR-2026-000779', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 100, 'total_amount' => 100,
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'tamara', 'method' => 'tamara',
            'status' => Payment::STATUS_PENDING, 'amount' => 100, 'currency' => 'SAR',
        ]);

        $request = Request::create('/webhooks/tamara', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer not-the-right-token',
        ], json_encode([
            'event_type' => 'order_approved',
            'order_id' => 'tam_1',
            'order_reference_id' => $order->order_number,
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = app(TamaraWebhookController::class)->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        Event::assertNotDispatched(OrderPaid::class);
    }
}
