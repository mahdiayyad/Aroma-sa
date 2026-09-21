<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

/**
 * The storefront side of the Tamara checklist: pre-checkout eligibility on the
 * payment step (grey-out + server-side enforcement), Tamara's order id being
 * persisted, the return from Tamara without the original session, and the
 * widgets.
 */
class TamaraCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLocationLookup();
        Cache::flush();

        config([
            'services.tamara.api_token' => 'tok',
            'services.tamara.base_url'  => 'https://api-sandbox.tamara.co',
        ]);
    }

    /** Cart + address + gift step done, ready to open the payment step. */
    private function readyToPay(): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1]);
        $this->post(route('checkout.address.store'), ['billing_address' => [
            'recipient_name' => 'Sara', 'email' => 'sara@example.com',
            'phone' => '+966500000000', 'location_code' => 'RAHA1234',
        ]]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);
    }

    private function payWithTamara(): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('checkout.payment.store'), [
            'gateway' => 'tamara', 'method' => 'tamara', 'shipping_method' => 'standard',
            'terms_accepted' => '1', 'email' => 'receipt@example.com',
        ]);
    }

    private function fakeTamara(bool $eligible): void
    {
        Http::fake([
            '*tamara.co/pre-checkout/v1/eligibility' => Http::response(['is_eligible' => $eligible], 200),
            '*tamara.co/checkout' => Http::response([
                'order_id' => 'tam-uuid-7', 'checkout_url' => 'https://checkout-sandbox.tamara.co/c/7',
            ], 200),
        ]);
    }

    /* Eligibility --------------------------------------------------------- */

    public function test_an_ineligible_customer_sees_tamara_greyed_out_with_a_reason(): void
    {
        $this->readyToPay();
        $this->fakeTamara(false);

        $this->get(route('checkout.payment'))
            ->assertOk()
            ->assertSee(__('checkout.tamara_not_available'));
    }

    public function test_an_eligible_customer_sees_tamara_enabled(): void
    {
        $this->readyToPay();
        $this->fakeTamara(true);

        $this->get(route('checkout.payment'))
            ->assertOk()
            ->assertDontSee(__('checkout.tamara_not_available'));
    }

    public function test_paying_with_tamara_is_refused_server_side_when_ineligible(): void
    {
        $this->readyToPay();
        $this->fakeTamara(false);

        $this->payWithTamara()
            ->assertRedirect(route('checkout.payment'))
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count(), 'no order is created for an ineligible Tamara attempt');
        Http::assertNotSent(function ($request) {
            return substr($request->url(), -9) === '/checkout';
        });
    }

    /* Persisted reference ------------------------------------------------- */

    public function test_the_tamara_order_id_is_stored_on_the_payment_for_later_operations(): void
    {
        $this->readyToPay();
        $this->fakeTamara(true);

        $this->payWithTamara()->assertRedirect('https://checkout-sandbox.tamara.co/c/7');

        $payment = Payment::where('gateway', 'tamara')->first();
        $this->assertNotNull($payment);
        $this->assertSame('tam-uuid-7', $payment->reference_number);
    }

    /* Return from Tamara -------------------------------------------------- */

    private function pendingTamaraOrder(string $reference): Order
    {
        $order = Order::create([
            'order_number' => 'AR-2026-000811', 'status' => Order::STATUS_PENDING,
            'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => ['recipient_name' => 'Sara'], 'shipping_address' => ['recipient_name' => 'Sara'],
            'subtotal' => 100, 'total_amount' => 100,
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'tamara', 'method' => 'tamara',
            'status' => Payment::STATUS_PENDING, 'amount' => 100, 'currency' => 'SAR',
            'reference_number' => $reference,
        ]);

        return $order;
    }

    public function test_returning_from_tamara_without_the_original_session_still_finalises_the_order(): void
    {
        // Fresh session (another browser / in-app webview): only the query string survives.
        $order = $this->pendingTamaraOrder('tam-uuid-9');
        Http::fake(['*tamara.co/*' => Http::response(['order_id' => 'tam-uuid-9', 'status' => 'authorised'], 200)]);

        $this->get(route('payment.callback', ['paymentStatus' => 'approved', 'orderId' => 'tam-uuid-9']))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_AUTHORIZED, $order->payment()->first()->status);
    }

    public function test_the_return_query_alone_cannot_mark_an_order_paid(): void
    {
        $order = $this->pendingTamaraOrder('tam-uuid-10');
        Http::fake(['*tamara.co/*' => Http::response(['order_id' => 'tam-uuid-10', 'status' => 'declined'], 200)]);

        $this->get(route('payment.callback', ['paymentStatus' => 'approved', 'orderId' => 'tam-uuid-10']))
            ->assertSessionHas('error');

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status, 'Tamara said declined — the query string is not proof of payment');
    }

    public function test_an_unknown_order_id_on_return_goes_back_to_the_cart(): void
    {
        Http::fake();

        $this->get(route('payment.callback', ['orderId' => 'not-a-real-order']))
            ->assertRedirect(route('cart.index'));
    }

    /* Widgets ------------------------------------------------------------- */

    public function test_the_widget_renders_on_the_product_page_with_the_public_key_and_page_language(): void
    {
        config(['services.tamara.public_key' => 'pk_sandbox_test']);
        $product = Product::factory()->create(['base_price' => 350, 'stock_quantity' => 5, 'is_active' => true]);

        $this->get(route('product.show', ['en', $product->slug]))
            ->assertOk()
            ->assertSee('<tamara-widget', false)
            ->assertSee('amount="350.00"', false)
            ->assertSee('pk_sandbox_test', false)
            ->assertSee('"en"', false)
            ->assertSee('cdn-sandbox.tamara.co/widget-v2/tamara-widget.js', false);

        $this->get(route('product.show', ['ar', $product->slug]))->assertSee('"ar"', false);
    }

    public function test_no_widget_without_a_public_key(): void
    {
        config(['services.tamara.public_key' => null]);
        $product = Product::factory()->create(['base_price' => 350, 'stock_quantity' => 5, 'is_active' => true]);

        $this->get(route('product.show', ['en', $product->slug]))
            ->assertOk()
            ->assertDontSee('<tamara-widget', false);
    }

    public function test_the_production_environment_uses_the_production_widget_script(): void
    {
        config(['services.tamara.public_key' => 'pk_live', 'services.tamara.base_url' => 'https://api.tamara.co']);
        $product = Product::factory()->create(['base_price' => 350, 'stock_quantity' => 5, 'is_active' => true]);

        $this->get(route('product.show', ['en', $product->slug]))
            ->assertSee('https://cdn.tamara.co/widget-v2/tamara-widget.js', false)
            ->assertDontSee('cdn-sandbox', false);
    }

    public function test_the_widget_renders_on_the_cart_page(): void
    {
        config(['services.tamara.public_key' => 'pk_sandbox_test']);
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 2]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('<tamara-widget', false)
            ->assertSee('amount="400.00"', false);
    }
}
