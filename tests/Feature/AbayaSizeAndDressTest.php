<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CheckoutService;
use Database\Seeders\ProductOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Abaya size selection (50–60) + the fixed 100 SAR "add a dress" add-on,
 * end to end: PDP validation → cart pricing → order snapshot.
 */
class AbayaSizeAndDressTest extends TestCase
{
    use RefreshDatabase;

    private Product $abaya;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::factory()->create(['slug' => 'abayas']);
        $this->abaya = Product::factory()->for($category)->create([
            'name' => ['en' => 'Noir Abaya', 'ar' => 'عباية سوداء'],
            'base_price' => 450,
            'stock_quantity' => 20,
            'has_variants' => false,
        ]);

        (new ProductOptionSeeder())->run();
        $this->abaya->load('options.values');
    }

    private function option(string $key)
    {
        return $this->abaya->options->firstWhere('key', $key);
    }

    private function sizeValueId(string $size): int
    {
        return $this->option('size')->values
            ->first(fn ($v) => $v->getTranslations('label')['en'] === $size)->id;
    }

    private function dressValueId(bool $withDress): int
    {
        return $this->option('dress_addon')->values
            ->first(fn ($v) => (float) $v->price_delta === ($withDress ? 100.0 : 0.0))->id;
    }

    private function closureValueId(): int
    {
        return $this->option('closure_style')->values->firstWhere('is_default', true)->id;
    }

    /** size + closure (both required) — the minimum every abaya add needs. */
    private function requiredBase(string $size): array
    {
        return [
            $this->option('size')->id => $this->sizeValueId($size),
            $this->option('closure_style')->id => $this->closureValueId(),
        ];
    }

    /* ---- size range -------------------------------------------------------- */

    public function test_every_size_from_50_through_60_is_available(): void
    {
        $labels = $this->option('size')->values
            ->where('is_active', true)
            ->map(fn ($v) => $v->getTranslations('label')['en'])
            ->sort()->values()->all();

        $this->assertSame(array_map('strval', range(50, 60)), $labels);
    }

    public function test_the_pdp_offers_all_11_sizes_as_a_dropdown(): void
    {
        $html = $this->get(route('product.show', ['ar', $this->abaya->slug]))->assertOk()->getContent();

        // Rendered as a <select>, not a button grid.
        $this->assertStringContainsString('name="options['.$this->option('size')->id.']"', $html);
        $this->assertStringContainsString('js-option-select', $html);

        foreach (range(50, 60) as $size) {
            $this->assertStringContainsString('<option value="'.$this->sizeValueId((string) $size).'"', $html);
        }
    }

    /* ---- required + tamper-proof ----------------------------------------- */

    public function test_size_is_required_before_adding_to_cart(): void
    {
        $this->postJson('/cart', [
            'product_id' => $this->abaya->id,
            'options'    => [
                $this->option('closure_style')->id => $this->closureValueId(),
                $this->option('dress_addon')->id => $this->dressValueId(false),
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('options.'.$this->option('size')->id);

        $this->assertEmpty(session('cart', []));
    }

    public function test_a_manipulated_size_value_is_rejected(): void
    {
        $foreign = Product::factory()->create();
        $foreignOption = $foreign->allOptions()->create([
            'key' => 'size', 'label' => ['en' => 'Size', 'ar' => 'مقاس'], 'type' => 'select',
            'is_required' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $foreignValue = $foreignOption->values()->create(['label' => ['en' => '99', 'ar' => '99'], 'price_delta' => 0, 'is_active' => true]);

        $this->postJson('/cart', [
            'product_id' => $this->abaya->id,
            'options'    => [
                $this->option('closure_style')->id => $this->closureValueId(),
                // a real size value id — but from another product
                $this->option('size')->id => $foreignValue->id,
            ],
        ])->assertStatus(422);

        $this->assertEmpty(session('cart', []));
    }

    /* ---- dress add-on pricing (server-side) ----------------------------- */

    public function test_without_dress_the_price_is_the_abaya_base_price(): void
    {
        $cart = new CartService();
        $cart->add($this->abaya->id, null, 1, $this->requiredBase('56') + [
            $this->option('dress_addon')->id => $this->dressValueId(false),
        ]);

        $row = array_values($cart->rows())[0];
        $this->assertSame(450.0, $row['unit_price']);
        $this->assertSame(450.0, $row['base_unit_price']);
    }

    public function test_adding_the_dress_adds_exactly_100_regardless_of_frontend(): void
    {
        $cart = new CartService();
        // Even if the client tampered with a data-price-delta attribute, only
        // the DB value is used.
        $cart->add($this->abaya->id, null, 1, $this->requiredBase('56') + [
            $this->option('dress_addon')->id => $this->dressValueId(true),
        ]);

        $row = array_values($cart->rows())[0];
        $this->assertSame(550.0, $row['unit_price']);   // 450 + 100
        $this->assertSame(450.0, $row['base_unit_price']);

        $dress = collect($row['options'])->firstWhere('key', 'dress_addon');
        $this->assertSame(100.0, $dress['price_delta']);
    }

    public function test_the_dress_addon_is_100_for_any_abaya_price(): void
    {
        $this->abaya->update(['base_price' => 1290]);

        $cart = new CartService();
        $cart->add($this->abaya->id, null, 1, $this->requiredBase('54') + [
            $this->option('dress_addon')->id => $this->dressValueId(true),
        ]);

        $this->assertSame(1390.0, array_values($cart->rows())[0]['unit_price']);
    }

    /* ---- order snapshot ------------------------------------------------- */

    public function test_size_and_dress_are_stored_on_the_order_and_survive_price_changes(): void
    {
        $this->app->setLocale('en');
        $cart = new CartService();
        $cart->add($this->abaya->id, null, 2, $this->requiredBase('58') + [
            $this->option('dress_addon')->id => $this->dressValueId(true),
        ]);

        $order = (new CheckoutService($cart))->createOrder(null, [
            'billing_address' => ['recipient_name' => 'Sara', 'phone' => '0500000000'],
            'customer_name' => 'Sara', 'customer_email' => 'sara@example.com', 'customer_phone' => '0500000000',
        ]);

        $item = $order->items->first();
        $this->assertEquals(550, $item->unit_price);
        $this->assertEquals(1100, $item->line_total);
        $this->assertSame(450.0, $item->baseUnitPrice());
        $this->assertSame(100.0, $item->addonTotal());
        $this->assertSame('58', $item->optionLines()['Size']);

        // Admin later raises the default add-on price — this order is unchanged.
        $this->option('dress_addon')->values()->where('price_delta', 100)->update(['price_delta' => 175]);
        $this->assertSame(100.0, $item->fresh()->addonTotal());
    }

    /* ---- admin can turn the add-on off --------------------------------- */

    public function test_disabling_the_dress_addon_removes_it_from_the_flow(): void
    {
        $this->option('dress_addon')->update(['is_active' => false]);

        $this->get(route('product.show', ['ar', $this->abaya->slug]))
            ->assertOk()
            ->assertDontSee('إضافة فستان مع العباية');

        // And it is no longer required — size + closure alone is enough.
        $this->post('/cart', [
            'product_id' => $this->abaya->id,
            'options'    => $this->requiredBase('55'),
        ])->assertRedirect();

        $this->assertCount(1, session('cart'));
        $this->assertNull(collect(array_values(session('cart'))[0]['options'])->firstWhere('key', 'dress_addon'));
    }
}
