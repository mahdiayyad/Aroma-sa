<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

/**
 * Proves the payment-step email genuinely reaches the receipt pipeline —
 * CheckoutController::storePayment() now sources Order.customer_email from
 * PaymentRequest's validated 'email' field (not $user->email, which can be
 * null for phone-only accounts, and not the old 'noemail@aroma.sa'
 * placeholder) — and that CheckoutService::markOrderAsPaid() (the single
 * paid-transition choke point) still fires OrderPaid -> SendOrderConfirmation
 * Email -> a queued OrderConfirmationMail addressed to exactly that email.
 */
class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLocationLookup();
    }

    public function test_a_paid_guest_order_queues_a_confirmation_email_to_the_checkout_email(): void
    {
        Mail::fake();
        config(['services.moyasar.secret_key' => 'sk_test']);
        // Both the invoice-create call (storePayment) and the status-check
        // call (payment.callback) hit api.moyasar.com — registered together
        // with the status-check's more specific path first, since Http::fake()
        // does not override an already-matching pattern on a later call.
        Http::fake([
            'api.moyasar.com/v1/invoices/inv_123' => Http::response(['id' => 'inv_123', 'status' => 'paid'], 200),
            'api.moyasar.com/*' => Http::response([
                'id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123',
            ], 200),
        ]);

        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1]);
        $this->post(route('checkout.address.store'), ['billing_address' => [
            'recipient_name' => 'Sara', 'email' => 'sara@example.com',
            'phone' => '+966500000000', 'location_code' => 'RAHA1234',
        ]]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);
        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard',
            'terms_accepted' => '1', 'email' => 'receipt@example.com',
        ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('receipt@example.com', $order->customer_email);

        // Gateway return path (the webhook can't reach a local test host).
        $this->get(route('payment.callback'));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);

        Mail::assertQueued(OrderConfirmationMail::class, function ($mail) use ($order) {
            return $mail->hasTo('receipt@example.com') && $mail->order->id === $order->id;
        });
    }
}
