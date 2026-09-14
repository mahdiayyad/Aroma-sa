<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLocationLookup();
    }

    private array $validBilling = [
        'recipient_name' => 'Sara Al Qahtani',
        'email'          => 'sara@example.com',
        'phone'          => '+966500000000',
        'location_code'  => 'RAHA1234',
    ];

    private function seedCart(int $qty = 1, float $price = 200): Product
    {
        $product = Product::factory()->create(['base_price' => $price, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => $qty])->assertRedirect();

        return $product;
    }

    private function completeAddressGiftAndDelivery(): void
    {
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);
        $this->post(route('checkout.delivery.store'), [
            'delivery_date'      => now()->addDays(2)->toDateString(),
            'delivery_time_slot' => Order::DELIVERY_SLOT_MORNING,
        ]);
    }

    /* ---- Apply/remove at Order Review ------------------------------------- */

    public function test_a_valid_code_can_be_applied_and_shows_a_discount(): void
    {
        PromoCode::factory()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);
        $this->seedCart(1, 200);
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'save10'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame('SAVE10', session('checkout.promo.code'));
        $this->assertEquals(20, session('checkout.promo.discount_amount'));

        $this->get(route('checkout.order-review'))->assertSee('SAVE10');
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'NOPE'])
            ->assertSessionHasErrors('code');

        $this->assertFalse(session()->has('checkout.promo'));
    }

    public function test_an_expired_code_is_rejected(): void
    {
        PromoCode::factory()->create(['code' => 'OLD10', 'expires_at' => now()->subDay()]);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'OLD10'])
            ->assertSessionHasErrors('code');
    }

    public function test_an_inactive_code_is_rejected(): void
    {
        PromoCode::factory()->create(['code' => 'OFF10', 'is_active' => false]);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'OFF10'])
            ->assertSessionHasErrors('code');
    }

    public function test_only_one_promo_code_can_be_applied_at_a_time(): void
    {
        PromoCode::factory()->create(['code' => 'FIRST10']);
        PromoCode::factory()->create(['code' => 'SECOND10']);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'FIRST10'])->assertSessionHas('status');
        $this->assertSame('FIRST10', session('checkout.promo.code'));

        $this->post(route('checkout.promo.apply'), ['code' => 'SECOND10'])->assertSessionHas('error');

        // Still the first code — the second was rejected, not stacked.
        $this->assertSame('FIRST10', session('checkout.promo.code'));
    }

    public function test_removing_a_promo_code_clears_the_session(): void
    {
        PromoCode::factory()->create(['code' => 'SAVE10']);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();
        $this->post(route('checkout.promo.apply'), ['code' => 'SAVE10']);

        $this->delete(route('checkout.promo.remove'))->assertSessionHas('status');

        $this->assertFalse(session()->has('checkout.promo'));
    }

    /* ---- Server-side eligibility checks ------------------------------------ */

    public function test_minimum_order_amount_is_enforced(): void
    {
        PromoCode::factory()->create(['code' => 'BIG500', 'min_order_amount' => 500]);
        $this->seedCart(1, 200);
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'BIG500'])
            ->assertSessionHasErrors('code');
    }

    public function test_maximum_discount_amount_caps_a_percentage_discount(): void
    {
        PromoCode::factory()->create([
            'code' => 'HUGE90', 'discount_type' => 'percentage', 'discount_value' => 90, 'max_discount_amount' => 50,
        ]);
        $this->seedCart(1, 200); // 90% of 200 = 180, capped at 50
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'HUGE90']);

        $this->assertEquals(50, session('checkout.promo.discount_amount'));
    }

    public function test_first_order_only_code_is_rejected_for_a_returning_customer(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id, 'status' => Order::STATUS_DELIVERED]);
        PromoCode::factory()->create(['code' => 'WELCOME15', 'first_order_only' => true]);

        $this->actingAs($user);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'WELCOME15'])
            ->assertSessionHasErrors('code');
    }

    public function test_first_order_only_code_works_for_a_genuine_first_time_customer(): void
    {
        $user = User::factory()->create();
        PromoCode::factory()->create(['code' => 'WELCOME15', 'discount_type' => 'percentage', 'discount_value' => 15]);

        $this->actingAs($user);
        $this->seedCart(1, 200);
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'WELCOME15'])
            ->assertSessionHas('status');

        $this->assertEquals(30, session('checkout.promo.discount_amount'));
    }

    public function test_first_order_only_code_requires_an_account(): void
    {
        PromoCode::factory()->create(['code' => 'WELCOME15', 'first_order_only' => true]);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'WELCOME15'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_customer_specific_code_rejects_other_customers(): void
    {
        $eligible = User::factory()->create();
        $stranger = User::factory()->create();
        $promo = PromoCode::factory()->create(['code' => 'VIPONLY', 'customer_restricted' => true]);
        $promo->customers()->attach($eligible->id);

        $this->actingAs($stranger);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'VIPONLY'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_customer_specific_code_works_for_the_assigned_customer(): void
    {
        $eligible = User::factory()->create();
        $promo = PromoCode::factory()->create(['code' => 'VIPONLY', 'customer_restricted' => true]);
        $promo->customers()->attach($eligible->id);

        $this->actingAs($eligible);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'VIPONLY'])
            ->assertSessionHas('status');
    }

    public function test_global_usage_limit_is_enforced(): void
    {
        $promo = PromoCode::factory()->create(['code' => 'LIMITED1', 'usage_limit' => 1, 'used_count' => 1]);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'LIMITED1'])
            ->assertSessionHasErrors('code');
    }

    public function test_per_customer_usage_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        $promo = PromoCode::factory()->create(['code' => 'ONCEEACH', 'usage_limit_per_customer' => 1]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $promo->redemptions()->create(['order_id' => $order->id, 'user_id' => $user->id, 'discount_amount' => 10]);

        $this->actingAs($user);
        $this->seedCart();
        $this->completeAddressGiftAndDelivery();

        $this->post(route('checkout.promo.apply'), ['code' => 'ONCEEACH'])
            ->assertSessionHasErrors('code');
    }

    /* ---- Full checkout + redemption bookkeeping ---------------------------- */

    public function test_placing_an_order_with_a_promo_freezes_the_discount_on_the_order(): void
    {
        config(['services.moyasar.secret_key' => 'sk_test']);
        Http::fake([
            'api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123'], 200),
        ]);

        PromoCode::factory()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);
        $this->seedCart(1, 200);
        $this->completeAddressGiftAndDelivery();
        $this->post(route('checkout.promo.apply'), ['code' => 'SAVE10']);

        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard', 'terms_accepted' => '1',
        ])->assertRedirect('https://moyasar.test/pay/inv_123');

        $order = Order::first();
        $this->assertSame('SAVE10', $order->promo_code);
        $this->assertEquals(20, $order->discount_amount);
        $this->assertEquals(180, $order->total_amount);

        // Not yet redeemed — the order is still pending, payment unconfirmed.
        $this->assertDatabaseCount('promo_code_redemptions', 0);
        $this->assertEquals(0, $order->promoCode->fresh()->used_count);
    }

    public function test_redemption_is_only_recorded_once_payment_is_confirmed(): void
    {
        config(['services.moyasar.secret_key' => 'sk_test']);
        $promo = PromoCode::factory()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $order = Order::factory()->create([
            'promo_code_id' => $promo->id, 'promo_code' => 'SAVE10',
            'subtotal' => 200, 'discount_amount' => 20, 'total_amount' => 180,
            'status' => Order::STATUS_PENDING,
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'moyasar', 'method' => 'mada',
            'status' => Payment::STATUS_PENDING, 'amount' => 180, 'currency' => 'SAR',
        ]);

        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'status' => 'paid'], 200)]);

        $this->withSession([
            'checkout.gateway' => 'moyasar', 'checkout.reference' => 'inv_123', 'checkout.order_id' => $order->id,
        ])->get(route('payment.callback'))->assertRedirect(route('home', 'ar'));

        $this->assertDatabaseCount('promo_code_redemptions', 1);
        $this->assertEquals(1, $promo->fresh()->used_count);
    }

    /* ---- Redemption idempotency (the race-condition guard) ----------------- */

    public function test_redeeming_the_same_order_twice_never_double_counts(): void
    {
        $promo = PromoCode::factory()->create(['code' => 'SAVE10', 'usage_limit' => 1]);
        $order = Order::factory()->create(['promo_code_id' => $promo->id]);
        $service = app(PromoCodeService::class);

        $service->redeem($promo, $order, null, 20);
        $service->redeem($promo, $order, null, 20); // simulates a retried webhook / double-dispatch

        $this->assertDatabaseCount('promo_code_redemptions', 1);
        $this->assertEquals(1, $promo->fresh()->used_count);
    }
}
