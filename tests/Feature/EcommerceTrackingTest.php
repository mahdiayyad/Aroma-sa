<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

/**
 * E-commerce events for the Meta Pixel / TikTok Pixel / GTM dataLayer, as
 * handed to the browser (public/js/aroma-tracking.js does the per-platform
 * mapping). Runs the real cart, checkout and payment-return flows.
 */
class EcommerceTrackingTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    private const BILLING = [
        'recipient_name' => 'Sara Al Qahtani',
        'phone' => '+966500000000',
        'location_code' => 'RAHA1234',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLocationLookup();
        app()->instance('env', 'production');
        // Laravel only skips CSRF checks when the environment is "testing".
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['services.moyasar.secret_key' => 'sk_test']);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'sku' => 'ABY-'.random_int(1000, 9999),
            'base_price' => 450,
            'stock_quantity' => 10,
        ], $attributes));
    }

    /** @return array{events:array<int,array<string,mixed>>,deferred:array<string,mixed>}|null */
    private function trackingData(TestResponse $response): ?array
    {
        if (! preg_match('#<script type="application/json" id="aromaTrackingData">(.*?)</script>#s', (string) $response->getContent(), $m)) {
            return null;
        }

        return json_decode($m[1], true);
    }

    /** @return array<int,array<string,mixed>> */
    private function pageEvents(TestResponse $response, ?string $name = null): array
    {
        $events = $this->trackingData($response)['events'] ?? [];

        return array_values(array_filter($events, function (array $event) use ($name) {
            return $name === null || $event['event'] === $name;
        }));
    }

    private function addToCartJson(Product $product, int $qty = 1): TestResponse
    {
        return $this->postJson('/cart', ['product_id' => $product->id, 'qty' => $qty])->assertOk();
    }

    private function rowId(Product $product): string
    {
        return (string) collect(session('cart'))->firstWhere('product_id', $product->id)['row_id'];
    }

    private function fakeMoyasar(string $returnStatus): void
    {
        Http::fake(function (HttpRequest $request) use ($returnStatus) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'inv_1', 'url' => 'https://checkout.moyasar.com/invoices/inv_1', 'status' => 'initiated'], 200);
            }

            return Http::response(['id' => 'inv_1', 'status' => $returnStatus], 200);
        });
    }

    /** Cart → address → gift → payment, ending with the redirect to Moyasar. */
    private function checkoutToGateway(Product $product, int $qty): void
    {
        $this->post('/cart', ['product_id' => $product->id, 'qty' => $qty]);
        $this->post(route('checkout.address.store'), ['billing_address' => self::BILLING]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);
        $this->post(route('checkout.payment.store'), [
            'gateway' => 'moyasar', 'method' => 'mada', 'shipping_method' => 'aramex',
            'terms_accepted' => '1', 'email' => 'sara@example.com',
        ])->assertRedirect('https://checkout.moyasar.com/invoices/inv_1');
    }

    /* ------------------------------------------------------------------ */
    /* Browsing                                                            */
    /* ------------------------------------------------------------------ */

    public function test_the_product_page_reports_view_content_with_catalog_data(): void
    {
        $category = Category::factory()->create(['name' => ['en' => 'Abayas', 'ar' => 'عبايات']]);
        $product = $this->product(['sku' => 'ABY-NOIR', 'name' => ['en' => 'Noir Abaya', 'ar' => 'عباية'], 'category_id' => $category->id]);

        $events = $this->pageEvents($this->get(route('product.show', ['ar', $product->slug]))->assertOk(), 'view_item');

        $this->assertCount(1, $events);
        $this->assertSame('SAR', $events[0]['ecommerce']['currency']);
        $this->assertEquals(450, $events[0]['ecommerce']['value']);
        $this->assertSame([
            'item_id' => 'ABY-NOIR',
            'item_name' => 'Noir Abaya',
            'item_category' => 'Abayas',
            'price' => 450,
            'quantity' => 1,
        ], $events[0]['ecommerce']['items'][0]);
        $this->assertNotEmpty($events[0]['event_id']);
    }

    public function test_the_category_page_reports_the_products_it_lists(): void
    {
        $category = Category::factory()->create(['name' => ['en' => 'Abayas', 'ar' => 'عبايات']]);
        $this->product(['sku' => 'ABY-1', 'category_id' => $category->id]);
        $this->product(['sku' => 'ABY-2', 'category_id' => $category->id]);

        $events = $this->pageEvents($this->get(route('category.show', ['en', $category->slug]))->assertOk(), 'view_item_list');

        $this->assertCount(1, $events);
        $this->assertSame('Abayas', $events[0]['ecommerce']['item_list_name']);
        $this->assertEqualsCanonicalizing(['ABY-1', 'ABY-2'], array_column($events[0]['ecommerce']['items'], 'item_id'));
    }

    /* ------------------------------------------------------------------ */
    /* Cart                                                                */
    /* ------------------------------------------------------------------ */

    public function test_an_ajax_add_to_cart_reports_what_was_added(): void
    {
        $product = $this->product(['sku' => 'ABY-NOIR']);

        $tracking = $this->addToCartJson($product, 2)->json('tracking');

        $this->assertCount(1, $tracking);
        $this->assertSame('add_to_cart', $tracking[0]['event']);
        $this->assertEquals(900, $tracking[0]['ecommerce']['value']);
        $this->assertSame('ABY-NOIR', $tracking[0]['ecommerce']['items'][0]['item_id']);
        $this->assertSame(2, $tracking[0]['ecommerce']['items'][0]['quantity']);
        $this->assertEquals(450, $tracking[0]['ecommerce']['items'][0]['price']);
    }

    public function test_only_the_units_actually_added_are_reported(): void
    {
        $product = $this->product(['stock_quantity' => 3]);

        $this->addToCartJson($product, 2);
        $tracking = $this->addToCartJson($product, 5)->json('tracking');

        // Stock caps the line at 3, so the second add only added one unit.
        $this->assertSame(1, $tracking[0]['ecommerce']['items'][0]['quantity']);
        $this->assertEquals(450, $tracking[0]['ecommerce']['value']);

        // Nothing more can be added: no event at all.
        $this->assertSame([], $this->addToCartJson($product, 1)->json('tracking'));
    }

    public function test_cart_quantity_changes_report_add_or_remove_for_the_difference(): void
    {
        $product = $this->product();
        $this->addToCartJson($product, 2);
        $url = route('cart.update', $this->rowId($product));

        $up = $this->patchJson($url, ['qty' => 5])->json('tracking');
        $this->assertSame('add_to_cart', $up[0]['event']);
        $this->assertSame(3, $up[0]['ecommerce']['items'][0]['quantity']);

        $down = $this->patchJson($url, ['qty' => 1])->json('tracking');
        $this->assertSame('remove_from_cart', $down[0]['event']);
        $this->assertSame(4, $down[0]['ecommerce']['items'][0]['quantity']);
        $this->assertEquals(1800, $down[0]['ecommerce']['value']);

        $gone = $this->patchJson($url, ['qty' => 0])->json('tracking');
        $this->assertSame('remove_from_cart', $gone[0]['event']);
        $this->assertSame(1, $gone[0]['ecommerce']['items'][0]['quantity']);
    }

    public function test_removing_a_line_reports_remove_from_cart(): void
    {
        $product = $this->product(['sku' => 'ABY-NOIR']);
        $this->addToCartJson($product, 2);

        $tracking = $this->deleteJson(route('cart.remove', $this->rowId($product)))->assertOk()->json('tracking');

        $this->assertSame('remove_from_cart', $tracking[0]['event']);
        $this->assertSame('ABY-NOIR', $tracking[0]['ecommerce']['items'][0]['item_id']);
        $this->assertSame(2, $tracking[0]['ecommerce']['items'][0]['quantity']);
    }

    public function test_a_non_ajax_add_is_reported_once_on_the_next_page(): void
    {
        $product = $this->product();

        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();

        $this->assertCount(1, $this->pageEvents($this->get('/en'), 'add_to_cart'));
        $this->assertCount(0, $this->pageEvents($this->get('/en'), 'add_to_cart'));
    }

    public function test_viewing_the_cart_reports_view_cart_with_its_contents(): void
    {
        $this->assertCount(0, $this->pageEvents($this->get(route('cart.index')), 'view_cart'));

        $product = $this->product();
        $this->addToCartJson($product, 2);

        $events = $this->pageEvents($this->get(route('cart.index'))->assertOk(), 'view_cart');
        $this->assertCount(1, $events);
        $this->assertEquals(900, $events[0]['ecommerce']['value']);
        $this->assertSame(2, $events[0]['ecommerce']['items'][0]['quantity']);
    }

    /* ------------------------------------------------------------------ */
    /* Checkout                                                            */
    /* ------------------------------------------------------------------ */

    public function test_initiate_checkout_fires_once_per_cart(): void
    {
        $product = $this->product();
        $this->addToCartJson($product, 1);

        $first = $this->pageEvents($this->get(route('checkout.review'))->assertOk(), 'begin_checkout');
        $this->assertCount(1, $first);
        $this->assertEquals(450, $first[0]['ecommerce']['value']);

        // Reloading, going back, or moving to the next step doesn't start a new checkout.
        $this->assertCount(0, $this->pageEvents($this->get(route('checkout.review')), 'begin_checkout'));
        $this->assertCount(0, $this->pageEvents($this->get(route('checkout.start')), 'begin_checkout'));

        // A changed cart is a new checkout.
        $this->addToCartJson($product, 1);
        $this->assertCount(1, $this->pageEvents($this->get(route('checkout.review')), 'begin_checkout'));
    }

    public function test_the_payment_page_prepares_add_payment_info_for_the_form_submit(): void
    {
        $product = $this->product(['sku' => 'ABY-NOIR']);
        $this->addToCartJson($product, 2);
        $this->post(route('checkout.address.store'), ['billing_address' => self::BILLING]);
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);

        $response = $this->get(route('checkout.payment'))->assertOk();
        $data = $this->trackingData($response);

        // Not fired on page load — only when the form is actually submitted.
        $this->assertCount(0, $this->pageEvents($response, 'add_payment_info'));
        $this->assertSame('add_payment_info', $data['deferred']['add_payment_info']['event']);
        $this->assertSame('ABY-NOIR', $data['deferred']['add_payment_info']['ecommerce']['items'][0]['item_id']);
        $response->assertSee('data-track-submit="add_payment_info"', false);
    }

    /* ------------------------------------------------------------------ */
    /* Purchase                                                            */
    /* ------------------------------------------------------------------ */

    public function test_a_paid_order_reports_purchase_exactly_once(): void
    {
        $this->fakeMoyasar('paid');
        $product = $this->product(['sku' => 'ABY-NOIR']);
        $this->checkoutToGateway($product, 2);

        // Clicking Place Order alone never reports a purchase.
        $order = Order::firstOrFail();
        $this->assertNull($order->purchase_tracked_at);

        // The gateway sends the shopper back; Moyasar confirms the payment.
        $this->get(route('payment.callback'))->assertRedirect(route('home', 'ar'));

        $purchases = $this->pageEvents($this->get('/ar')->assertOk(), 'purchase');
        $order->refresh();

        $this->assertCount(1, $purchases);
        $this->assertSame('purchase-'.$order->order_number, $purchases[0]['event_id']);
        $this->assertTrue($purchases[0]['once']);
        $this->assertSame($order->order_number, $purchases[0]['ecommerce']['transaction_id']);
        $this->assertEquals((float) $order->total_amount, $purchases[0]['ecommerce']['value']);
        $this->assertSame('SAR', $purchases[0]['ecommerce']['currency']);
        $this->assertSame('ABY-NOIR', $purchases[0]['ecommerce']['items'][0]['item_id']);
        $this->assertSame(2, $purchases[0]['ecommerce']['items'][0]['quantity']);
        $this->assertEquals(450, $purchases[0]['ecommerce']['items'][0]['price']);
        $this->assertNotNull($order->purchase_tracked_at);

        // Refreshing the page, or the gateway return being replayed, never repeats it.
        $this->assertCount(0, $this->pageEvents($this->get('/ar'), 'purchase'));
        $this->withSession(['checkout.gateway' => 'moyasar', 'checkout.reference' => 'inv_1', 'checkout.order_id' => $order->id])
            ->get(route('payment.callback'))->assertRedirect(route('home', 'ar'));
        $this->assertCount(0, $this->pageEvents($this->get('/ar'), 'purchase'));
    }

    public function test_a_failed_or_cancelled_payment_never_reports_purchase(): void
    {
        $this->fakeMoyasar('failed');
        $product = $this->product();
        $this->checkoutToGateway($product, 1);

        $this->get(route('payment.callback'))->assertRedirect(route('checkout.payment'));

        $this->assertCount(0, $this->pageEvents($this->get(route('checkout.payment')), 'purchase'));
        $this->assertNull(Order::firstOrFail()->purchase_tracked_at);
    }

    public function test_an_order_confirmed_by_webhook_reports_purchase_when_the_shopper_comes_back(): void
    {
        $this->fakeMoyasar('initiated');
        $product = $this->product();
        $this->checkoutToGateway($product, 1);

        // The webhook marks the order paid before this browser returns.
        $order = Order::firstOrFail();
        $order->update(['status' => Order::STATUS_PAID]);

        $this->get(route('checkout.payment'))->assertRedirect(route('home', 'ar'));
        $this->assertCount(1, $this->pageEvents($this->get('/ar'), 'purchase'));
        $this->assertNotNull($order->fresh()->purchase_tracked_at);
    }

    /* ------------------------------------------------------------------ */
    /* Wishlist, scope                                                     */
    /* ------------------------------------------------------------------ */

    public function test_adding_to_the_wishlist_is_reported_but_removing_is_not(): void
    {
        $product = $this->product(['sku' => 'ABY-NOIR']);
        $this->actingAs(User::factory()->create());

        $added = $this->postJson(route('wishlist.toggle', $product->slug))->assertOk()->json('tracking');
        $this->assertSame('add_to_wishlist', $added[0]['event']);
        $this->assertSame('ABY-NOIR', $added[0]['ecommerce']['items'][0]['item_id']);

        $this->assertSame([], $this->postJson(route('wishlist.toggle', $product->slug))->assertOk()->json('tracking'));
    }

    public function test_nothing_is_tracked_outside_production(): void
    {
        app()->instance('env', 'local');
        $product = $this->product();

        $this->assertSame([], $this->addToCartJson($product, 1)->json('tracking'));
        $this->assertNull($this->trackingData($this->get(route('product.show', ['ar', $product->slug]))));
    }

    public function test_staff_browsing_the_store_are_not_tracked(): void
    {
        $product = $this->product();

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]))
            ->withSession(['admin_store_preview' => true])
            ->get(route('product.show', ['ar', $product->slug]));

        $this->assertNull($this->trackingData($response));
    }
}
