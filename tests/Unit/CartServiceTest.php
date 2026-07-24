<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cart is session-backed and stores a snapshot of each line so it stays
 * stable across catalogue/locale changes. These tests drive the service
 * directly (the HTTP layer is covered separately in tests/Feature/CartTest).
 */
class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cart = new CartService();
        $this->cart->clear();
    }

    public function test_it_adds_a_product_and_snapshots_the_line(): void
    {
        $product = Product::factory()->create([
            'name'           => ['en' => 'Amber Nights', 'ar' => 'ليالي العنبر'],
            'base_price'     => 250,
            'stock_quantity' => 10,
        ]);

        $this->cart->add($product->id, null, 2);

        $rows = array_values($this->cart->rows());

        $this->assertCount(1, $rows);
        $this->assertSame($product->id, $rows[0]['product_id']);
        $this->assertSame(2, $rows[0]['qty']);
        $this->assertSame(250.0, $rows[0]['unit_price']);
        // Snapshot keeps both translations so the cart is locale-independent.
        $this->assertSame('Amber Nights', $rows[0]['name']['en']);
        $this->assertSame('ليالي العنبر', $rows[0]['name']['ar']);
    }

    public function test_adding_the_same_product_merges_quantities(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->cart->add($product->id, null, 1);
        $this->cart->add($product->id, null, 3);

        $this->assertSame(4, $this->cart->count());
        $this->assertCount(1, $this->cart->rows());
    }

    public function test_quantity_is_capped_at_available_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->cart->add($product->id, null, 10);

        $this->assertSame(3, $this->cart->count());
    }

    public function test_quantity_is_floored_at_one(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $this->cart->add($product->id, null, 0);

        $this->assertSame(1, $this->cart->count());
    }

    public function test_a_variant_line_uses_the_variant_price_and_stock(): void
    {
        $product = Product::factory()->create([
            'base_price'     => 100,
            'has_variants'   => true,
            'stock_quantity' => 0,
        ]);

        /** @var ProductVariant $variant */
        $variant = $product->variants()->create([
            'name'           => ['en' => '100ml', 'ar' => '100 مل'],
            'price'          => 175,
            'stock_quantity' => 2,
        ]);

        $this->cart->add($product->id, $variant->id, 5);

        $row = array_values($this->cart->rows())[0];

        $this->assertSame($variant->id, $row['variant_id']);
        $this->assertSame(175.0, $row['unit_price']);
        $this->assertSame(2, $row['qty']); // capped at the variant's stock
    }

    public function test_update_changes_the_quantity(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $this->cart->add($product->id, null, 1);
        $rowId = array_key_first($this->cart->rows());

        $this->cart->update($rowId, 5);

        $this->assertSame(5, $this->cart->count());
    }

    public function test_update_to_zero_removes_the_line(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $this->cart->add($product->id, null, 2);
        $rowId = array_key_first($this->cart->rows());

        $this->cart->update($rowId, 0);

        $this->assertEmpty($this->cart->rows());
    }

    public function test_update_ignores_unknown_rows(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $this->cart->add($product->id, null, 2);

        $this->cart->update('does-not-exist', 9);

        $this->assertSame(2, $this->cart->count());
    }

    public function test_remove_and_clear_empty_the_cart(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $this->cart->add($product->id, null, 2);
        $rowId = array_key_first($this->cart->rows());

        $this->cart->remove($rowId);
        $this->assertEmpty($this->cart->rows());

        $this->cart->add($product->id, null, 1);
        $this->cart->clear();
        $this->assertEmpty($this->cart->rows());
    }

    public function test_subtotal_sums_price_times_quantity_across_lines(): void
    {
        $a = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 10]);
        $b = Product::factory()->create(['base_price' => 50, 'stock_quantity' => 10]);

        $this->cart->add($a->id, null, 2); // 200
        $this->cart->add($b->id, null, 3); // 150

        $this->assertSame(350.0, $this->cart->subtotal());
    }

    public function test_subtotal_label_is_formatted_currency(): void
    {
        $this->app->setLocale('en');
        $product = Product::factory()->create(['base_price' => 120, 'stock_quantity' => 10]);
        $this->cart->add($product->id, null, 1);

        $this->assertSame('ر.س 120.00', $this->cart->subtotalLabel());
    }

    public function test_adding_a_missing_product_throws(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->cart->add(999999);
    }
}
