<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLocationLookup();
    }

    private function seedCart(int $qty = 1): Product
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => $qty])->assertRedirect();

        return $product;
    }

    private array $validBilling = [
        'recipient_name' => 'Sara Al Qahtani',
        'email'          => 'sara@example.com',
        'phone'          => '+966500000000',
        'location_code'  => 'RAHA1234',
    ];

    /**
     * Payment now requires the gift-options step to have actually been
     * completed first (see CheckoutController::missingStepRedirect) —
     * "not a gift" is a complete, valid answer to that step, so this is
     * the minimal real submission, not a workaround around the gate these
     * tests exist alongside.
     */
    private function completeGiftStep(): void
    {
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);
    }

    /* ---- The reported bug: shipping_address must default to billing -------- */

    public function test_storing_an_address_defaults_shipping_to_billing(): void
    {
        $this->seedCart();

        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling])
            ->assertRedirect(route('checkout.gift-options'))
            ->assertSessionHasNoErrors();

        $this->assertSame(session('checkout.billing_address'), session('checkout.shipping_address'));
        $this->assertSame('Riyadh', session('checkout.shipping_address')['city']);
    }

    public function test_guest_must_provide_an_email(): void
    {
        $this->seedCart();

        $billing = $this->validBilling;
        unset($billing['email']);

        $this->post(route('checkout.address.store'), ['billing_address' => $billing])
            ->assertSessionHasErrors('billing_address.email');
    }

    public function test_a_pinned_location_can_replace_the_location_code(): void
    {
        $this->seedCart();

        $billing = $this->validBilling;
        unset($billing['location_code']);
        // Strings, not floats: a real browser form submission sends every
        // field as a string — $request->validated()'s 'numeric' rule
        // validates but never casts. A float literal here would silently
        // skip over the exact TypeError this test exists to catch (see
        // CheckoutController::toFloatOrNull()).
        $billing['latitude'] = '24.7136';
        $billing['longitude'] = '46.6753';

        $this->post(route('checkout.address.store'), ['billing_address' => $billing])
            ->assertRedirect(route('checkout.gift-options'))
            ->assertSessionHasNoErrors();

        $shipping = session('checkout.shipping_address');
        $this->assertNull($shipping['location_code']);
        $this->assertNull($shipping['city']);
        $this->assertNull($shipping['formatted_address']);
        $this->assertIsFloat($shipping['latitude']);
        $this->assertIsFloat($shipping['longitude']);
        $this->assertEqualsWithDelta(24.7136, $shipping['latitude'], 0.0001);
        $this->assertEqualsWithDelta(46.6753, $shipping['longitude'], 0.0001);
    }

    public function test_neither_a_code_nor_coordinates_is_rejected(): void
    {
        $this->seedCart();

        $billing = $this->validBilling;
        unset($billing['location_code']);

        $this->post(route('checkout.address.store'), ['billing_address' => $billing])
            ->assertSessionHasErrors('billing_address.location_code');
    }

    private array $validManualBilling = [
        'recipient_name'  => 'Sara Al Qahtani',
        'email'           => 'sara@example.com',
        'phone'           => '+966500000000',
        'method'          => \App\Models\Address::METHOD_MANUAL,
        'country'         => 'SA',
        'city'            => 'Jeddah',
        'district'        => 'Al Rawdah',
        'street_address'  => 'King Fahd Road',
        'building_number' => '1234',
    ];

    public function test_a_shopper_can_check_out_with_a_manual_billing_address(): void
    {
        $this->seedCart();

        $this->post(route('checkout.address.store'), ['billing_address' => $this->validManualBilling])
            ->assertRedirect(route('checkout.gift-options'))
            ->assertSessionHasNoErrors();

        $billing = session('checkout.billing_address');
        $this->assertSame(\App\Models\Address::METHOD_MANUAL, $billing['method']);
        $this->assertSame('1234', $billing['building_number']);
        $this->assertSame('Jeddah', $billing['city']);
        $this->assertNull($billing['location_code']);
        // formatted_address is built server-side, not trusted from the client.
        $this->assertNotEmpty($billing['formatted_address']);
    }

    public function test_a_manual_billing_address_requires_the_building_number(): void
    {
        $this->seedCart();

        $billing = $this->validManualBilling;
        unset($billing['building_number']);

        $this->post(route('checkout.address.store'), ['billing_address' => $billing])
            ->assertSessionHasErrors('billing_address.building_number');
    }

    public function test_a_manual_shipping_address_is_validated_independently_of_billing(): void
    {
        $this->seedCart();

        $shipping = $this->validManualBilling;
        unset($shipping['email'], $shipping['city']);

        $this->post(route('checkout.address.store'), [
            'billing_address' => $this->validBilling,
            'use_shipping_for_billing' => '0',
            'shipping_address' => $shipping,
        ])->assertSessionHasErrors('shipping_address.city');
    }

    public function test_placing_an_order_with_a_manual_address_stores_it_on_the_order(): void
    {
        config(['services.moyasar.secret_key' => 'sk_test']);
        Http::fake([
            'api.moyasar.com/*' => Http::response([
                'id'    => 'inv_123',
                'url'   => 'https://moyasar.test/pay/inv_123',
                'token' => 'tok_123',
            ], 200),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validManualBilling]);
        $this->completeGiftStep();

        $this->post(route('checkout.payment.store'), [
            'gateway'         => 'moyasar',
            'method'          => 'mada',
            'shipping_method' => 'standard',
            'terms_accepted'  => '1',
            'email'           => 'sara@example.com',
        ])->assertRedirect('https://moyasar.test/pay/inv_123');

        $order = Order::first();
        $this->assertSame(\App\Models\Address::METHOD_MANUAL, $order->billing_address['method']);
        $this->assertSame('1234', $order->billing_address['building_number']);
        $this->assertSame('King Fahd Road', $order->billing_address['street_address']);
    }

    /* ---- Terms & Conditions open in a modal, not a navigation away --------- */

    public function test_payment_page_renders_with_a_terms_modal_not_a_link_away(): void
    {
        $this->seedCart();
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->completeGiftStep();

        // Note: the page footer legitimately still links to /terms directly
        // (a real "leave the site to read terms" context) — this only checks
        // that the checkout *form itself* opens a modal now, not that the
        // route never appears anywhere on the page.
        $this->get(route('checkout.payment'))
            ->assertOk()
            ->assertSee('id="termsModal"', false) // the modal exists on this page...
            ->assertSee('data-bs-target="#termsModal"', false) // ...triggered from within the form...
            ->assertSee(__('checkout.agree_terms_link')); // ...via a real, translated label
    }

    public function test_the_terms_page_itself_still_renders_standalone(): void
    {
        // The footer links here directly — a legitimate "leave checkout to
        // read terms" context distinct from the in-checkout modal above, so
        // this route/view must keep working on its own.
        $this->get(route('terms'))->assertOk()->assertSee('Aroma');
    }

    public function test_placing_an_order_without_accepting_terms_is_rejected(): void
    {
        $this->seedCart();
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);

        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard',
            // terms_accepted deliberately omitted
        ])->assertSessionHasErrors('terms_accepted');

        $this->assertDatabaseCount('orders', 0);
    }

    /* ---- Step-skipping: gift can't be bypassed ------------------------------ */

    public function test_jumping_straight_to_payment_without_completing_gift_redirects_back(): void
    {
        $this->seedCart();
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        // Deliberately not completing gift-options.

        $this->get(route('checkout.payment'))->assertRedirect(route('checkout.gift-options'));

        // A direct POST is gated the same way as the GET, even when the
        // request itself is otherwise fully valid (gateway/method/shipping/
        // terms/email all present) — missingStepRedirect() runs inside
        // storePayment() after PaymentRequest validation already passed.
        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard',
            'terms_accepted' => '1', 'email' => 'sara@example.com',
        ])->assertRedirect(route('checkout.gift-options'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_payment_submission_without_an_email_is_rejected(): void
    {
        $this->seedCart();
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->completeGiftStep();

        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard',
            'terms_accepted' => '1',
            // email deliberately omitted
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * The real gap this field closes: an authenticated, phone-only
     * (OTP-registered) account has users.email === null by design, and
     * previously fell through storePayment()'s old fallback chain straight
     * into a NOT NULL customer_email column — a DB exception swallowed by a
     * generic catch, silently failing order placement with no indication
     * the real cause was a missing email. Now it's a normal, visible
     * validation error, and supplying one at payment succeeds.
     */
    public function test_an_authenticated_user_with_no_account_email_must_supply_one_at_payment(): void
    {
        config(['services.moyasar.secret_key' => 'sk_test']);
        Http::fake([
            'api.moyasar.com/*' => Http::response([
                'id' => 'inv_123', 'url' => 'https://moyasar.test/pay/inv_123', 'token' => 'tok_123',
            ], 200),
        ]);

        $user = User::factory()->create(['email' => null]);
        $this->actingAs($user);
        $this->seedCart();
        $billing = $this->validBilling;
        unset($billing['email']); // nullable for authenticated users
        $this->post(route('checkout.address.store'), ['billing_address' => $billing]);
        $this->completeGiftStep();

        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard', 'terms_accepted' => '1',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'standard', 'terms_accepted' => '1',
            'email' => 'phoneonly@example.com',
        ])->assertRedirect('https://moyasar.test/pay/inv_123');

        $this->assertSame('phoneonly@example.com', Order::first()->customer_email);
    }

    /* ---- Full happy path creates an order and remembers it ---------------- */

    public function test_placing_an_order_creates_it_and_redirects_to_the_gateway(): void
    {
        config(['services.moyasar.secret_key' => 'sk_test']);
        Http::fake([
            'api.moyasar.com/*' => Http::response([
                'id'    => 'inv_123',
                'url'   => 'https://moyasar.test/pay/inv_123',
                'token' => 'tok_123',
            ], 200),
        ]);

        $this->seedCart(2);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->completeGiftStep();

        $this->post(route('checkout.payment.store'), [
            'gateway'         => 'moyasar',
            'method'          => 'mada',
            'shipping_method' => 'standard',
            'terms_accepted'  => '1',
            'email'           => 'sara@example.com',
        ])->assertRedirect('https://moyasar.test/pay/inv_123');

        $this->assertDatabaseCount('orders', 1);

        $order = Order::first();
        $this->assertSame('sara@example.com', $order->customer_email);
        $this->assertEquals(400, $order->total_amount);
        $this->assertContains($order->id, session('checkout.completed_orders'));
        $this->assertEmpty(session('cart', [])); // cart cleared
    }

    /**
     * Regression coverage: cart-clear, checkout-session forget(), and the
     * "this order is viewable" session flag all used to fire unconditionally
     * BEFORE the gateway was ever called — so a failed gateway call left the
     * customer bounced to an already-empty cart (their real error message
     * overwritten by validateCart()'s "cart is empty" redirect), with no
     * coherent way to retry, while the unpaid order was still viewable at
     * its "confirmed" URL. See CheckoutController::storePayment.
     */
    public function test_a_failed_gateway_response_leaves_the_cart_and_session_intact(): void
    {
        config(['services.moyasar.secret_key' => 'sk_test']);
        Http::fake([
            'api.moyasar.com/*' => Http::response(['message' => 'insufficient funds'], 422),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->completeGiftStep();

        $this->post(route('checkout.payment.store'), [
            'gateway'         => 'moyasar',
            'method'          => 'mada',
            'shipping_method' => 'standard',
            'terms_accepted'  => '1',
            'email'           => 'sara@example.com',
        ])->assertRedirect(route('checkout.payment'))->assertSessionHas('error');

        // A pending order/payment row is still created (recoverable later via
        // the webhook or an admin) — but nothing is exposed to this session
        // as "completed", and the cart/checkout-session state is left alone
        // so the payment page the customer lands back on can actually be
        // retried instead of bouncing them to an empty-cart redirect.
        $this->assertDatabaseCount('orders', 1);
        $this->assertEmpty(session('checkout.completed_orders', []));
        $this->assertNotEmpty(session('cart', []));
        $this->assertNotEmpty(session('checkout.billing_address'));

        // The raw gateway error is never what gets flashed to the customer.
        $this->assertSame(__('checkout.errors.payment_failed'), session('error'));
    }

    public function test_completed_payment_marks_the_order_paid_and_redirects_home(): void
    {
        config(['services.moyasar.secret_key' => 'sk_test']);

        // A still-pending order (the webhook can't reach a local host).
        $order = $this->makeGuestOrder();
        $this->assertTrue($order->isPending());

        // Moyasar reports the invoice paid on the return status check.
        Http::fake([
            'api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'status' => 'paid'], 200),
        ]);

        $this->withSession([
            'checkout.gateway'   => 'moyasar',
            'checkout.reference' => 'inv_123',
            'checkout.order_id'  => $order->id,
        ])
            ->get(route('payment.callback'))
            ->assertRedirect(route('home', 'ar'))
            ->assertSessionHas('status');

        // The order is now finalised, so "My Orders" shows Paid, not Pending.
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_choosing_tabby_redirects_to_the_tabby_checkout(): void
    {
        config(['services.tabby.secret_key' => 'sk_tabby', 'services.tabby.merchant_code' => 'aroma']);
        Http::fake([
            'api.tabby.ai/*' => Http::response([
                'status'  => 'created',
                'payment' => ['id' => 'pay_tabby_1'],
                'configuration' => ['available_products' => ['installments' => [
                    ['web_url' => 'https://checkout.tabby.ai/pay/pay_tabby_1'],
                ]]],
            ], 200),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->completeGiftStep();

        $this->post(route('checkout.payment.store'), [
            'gateway' => 'tabby', 'method' => 'tabby', 'shipping_method' => 'standard', 'terms_accepted' => '1',
            'email' => 'sara@example.com',
        ])->assertRedirect('https://checkout.tabby.ai/pay/pay_tabby_1');

        $this->assertDatabaseHas('payments', ['gateway' => 'tabby']);
        $this->assertSame('tabby', session('checkout.gateway'));
    }

    public function test_choosing_tamara_redirects_to_the_tamara_checkout(): void
    {
        config(['services.tamara.api_token' => 'tok_tamara']);
        Http::fake([
            '*tamara.co/*' => Http::response([
                'order_id'     => 'tamara_order_1',
                'checkout_id'  => 'chk_1',
                'checkout_url' => 'https://checkout.tamara.co/c/chk_1',
            ], 200),
        ]);

        $this->seedCart(1);
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
        $this->completeGiftStep();

        $this->post(route('checkout.payment.store'), [
            'gateway' => 'tamara', 'method' => 'tamara', 'shipping_method' => 'standard', 'terms_accepted' => '1',
            'email' => 'sara@example.com',
        ])->assertRedirect('https://checkout.tamara.co/c/chk_1');

        $this->assertDatabaseHas('payments', ['gateway' => 'tamara']);
    }

    public function test_a_tabby_return_finalises_the_order(): void
    {
        config(['services.tabby.secret_key' => 'sk_tabby']);
        $order = $this->makeGuestOrder();

        Http::fake([
            'api.tabby.ai/*' => Http::response(['id' => 'pay_tabby_1', 'status' => 'AUTHORIZED'], 200),
        ]);

        $this->withSession([
            'checkout.gateway'   => 'tabby',
            'checkout.reference' => 'pay_tabby_1',
            'checkout.order_id'  => $order->id,
        ])
            ->get(route('payment.callback'))
            ->assertRedirect(route('home', 'ar'));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    /* ---- Confirmation authorisation (IDOR) -------------------------------- */

    private function makeGuestOrder(): Order
    {
        return Order::create([
            'user_id'         => null,
            'order_number'    => 'AR-2026-000999',
            'status'          => Order::STATUS_PENDING,
            'customer_name'   => 'Sara',
            'customer_email'  => 'sara@example.com',
            'customer_phone'  => '+966500000000',
            'billing_address' => $this->validBilling,
            'shipping_address' => $this->validBilling,
            'subtotal'        => 200,
            'total_amount'    => 200,
        ]);
    }

    public function test_a_stranger_cannot_view_someone_elses_confirmation(): void
    {
        $order = $this->makeGuestOrder();

        $this->get(route('order.confirmation', $order))->assertForbidden();
    }

    public function test_the_buyer_can_view_their_confirmation_via_the_session(): void
    {
        $order = $this->makeGuestOrder();

        $this->withSession(['checkout.completed_orders' => [$order->id]])
            ->get(route('order.confirmation', $order))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_a_logged_in_user_cannot_view_another_users_confirmation(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeGuestOrder();
        $order->update(['user_id' => $owner->id]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('order.confirmation', $order))
            ->assertForbidden();
    }
}
