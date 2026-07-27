<?php

namespace Tests\Unit;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payment\MoyasarPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        $checkout = new CheckoutService($cart);
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
        $checkout = new CheckoutService($cart);
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
}
