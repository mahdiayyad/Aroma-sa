<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

/**
 * CheckoutService::calculateTotals() used to hardcode shipping_cost to 0 —
 * this covers the real Aramex-quote wiring, the config-driven flat-rate
 * fallback when Aramex can't be reached, and the free_shipping promo flag
 * (set on PromoCode/session since before this change but never actually
 * read until now).
 */
class CheckoutShippingCostTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLocationLookup();

        config(['services.aramex' => array_merge(config('services.aramex'), [
            'base_url' => 'https://ws.dev.aramex.net/ShippingAPI.V2',
            'username' => 'api@aroma.test',
            'password' => 'secret-pass',
            'account_number' => '20016',
            'account_pin' => '331421',
            'account_entity' => 'RUH',
            'default_item_weight_kg' => 1.0,
            'shipper' => [
                'name' => 'Aroma', 'company' => 'Aroma Gift Center', 'phone' => '+966500000000',
                'email' => 'ops@aroma.test', 'line1' => 'King Fahd Rd', 'line2' => null,
                'city' => 'Riyadh', 'post_code' => '12211', 'country_code' => 'SA',
            ],
        ])]);
        config(['services.moyasar.secret_key' => 'sk_test']);
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123'], 200)]);
    }

    private array $validBilling = [
        'recipient_name' => 'Sara Al Qahtani',
        'email'          => 'sara@example.com',
        'phone'          => '+966500000000',
        'location_code'  => 'RAHA1234', // resolves to Riyadh, see MocksLocationLookup
    ];

    private function seedCart(int $qty = 1): Product
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => $qty])->assertRedirect();

        return $product;
    }

    private function placeOrder(): Order
    {
        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard', 'terms_accepted' => '1',
            'email' => 'sara@example.com',
        ])->assertRedirect('https://moyasar.test/pay/inv_123');

        return Order::first();
    }

    public function test_a_domestic_order_is_charged_the_real_aramex_quoted_rate(): void
    {
        Http::fake([
            'api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123'], 200),
            '*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 27.5]]),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);

        $order = $this->placeOrder();

        $this->assertEquals(27.5, (float) $order->shipping_cost);
        $this->assertEquals(200 + 27.5, (float) $order->total_amount);

        Http::assertSent(function ($request) {
            return strpos($request->url(), 'CalculateRate') !== false
                && $request['DestinationAddress']['City'] === 'Riyadh'
                && $request['DestinationAddress']['CountryCode'] === 'SA';
        });
    }

    public function test_a_failed_aramex_quote_falls_back_to_the_configured_flat_rate(): void
    {
        config(['aroma.shipping.domestic_flat_rate' => 19.0]);
        Http::fake([
            'api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123'], 200),
            '*/CalculateRate' => Http::response(['HasErrors' => true, 'Notifications' => [['Code' => 'ERR52', 'Message' => 'Invalid city']]]),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);

        $order = $this->placeOrder();

        $this->assertEquals(19.0, (float) $order->shipping_cost);
    }

    public function test_shipping_is_free_when_the_applied_promo_grants_free_shipping(): void
    {
        // free_shipping is cast on PromoCode and already written into the
        // session by applyPromo() — this directly exercises that it's now
        // actually honoured rather than silently ignored.
        Http::fake([
            'api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123'], 200),
            '*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 27.5]]),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);
        session(['checkout.promo.free_shipping' => true]);

        $order = $this->placeOrder();

        $this->assertEquals(0.0, (float) $order->shipping_cost);
        $this->assertEquals(200, (float) $order->total_amount);
        Http::assertNotSent(function ($request) {
            return strpos($request->url(), 'CalculateRate') !== false;
        });
    }

    public function test_an_international_order_is_charged_the_real_aramex_international_rate(): void
    {
        config(['aroma.shipping.international_flat_rate' => 45.0]);
        Http::fake([
            'api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123'], 200),
            '*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 120.0]]),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => [
            'recipient_name' => 'Sara Al Qahtani', 'email' => 'sara@example.com', 'phone' => '+971500000000',
            'method' => \App\Models\Address::METHOD_MANUAL, 'country' => 'AE', 'city' => 'Dubai',
            'street_address' => 'Sheikh Zayed Road',
        ]]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);

        $order = $this->placeOrder();

        $this->assertSame('AE', $order->billing_address['country']);
        $this->assertEquals(120.0, (float) $order->shipping_cost);

        Http::assertSent(function ($request) {
            return strpos($request->url(), 'CalculateRate') !== false
                && $request['DestinationAddress']['CountryCode'] === 'AE'
                && $request['DestinationAddress']['City'] === 'Dubai';
        });
    }

    public function test_no_shipping_is_charged_before_an_address_has_been_chosen(): void
    {
        Http::fake(['*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 27.5]])]);

        $this->seedCart(1);

        $this->get(route('checkout.order-review'))->assertRedirect(route('checkout.address'));
        Http::assertNothingSent();
    }

    public function test_the_payment_page_shows_no_duration_for_domestic_and_20_days_for_international(): void
    {
        Http::fake(['*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 20.0]])]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);

        $this->get(route('checkout.payment'))
            ->assertOk()
            ->assertDontSee(__('checkout.shipping_days.international'));

        session(['checkout.shipping_address' => array_merge(session('checkout.shipping_address'), ['country' => 'AE'])]);

        $this->get(route('checkout.payment'))
            ->assertOk()
            ->assertSee(__('checkout.shipping_days.international'));
    }
}
