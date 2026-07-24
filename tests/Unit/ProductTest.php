<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Pure-logic coverage for the Product presentation helpers. These methods are
 * deterministic given the model's attributes/relations, so the tests build
 * in-memory models and never touch the database.
 */
class ProductTest extends TestCase
{
    private function makeProduct(array $attributes = []): Product
    {
        $product = new Product();
        $product->forceFill(array_merge([
            'base_price'       => 100,
            'compare_at_price' => null,
            'stock_quantity'   => 5,
            'has_variants'     => false,
        ], $attributes));

        return $product;
    }

    /* isOnSale ------------------------------------------------------------- */

    public function test_it_is_on_sale_when_compare_at_price_is_higher(): void
    {
        $product = $this->makeProduct(['base_price' => 80, 'compare_at_price' => 120]);

        $this->assertTrue($product->isOnSale());
    }

    public function test_it_is_not_on_sale_without_a_compare_at_price(): void
    {
        $product = $this->makeProduct(['compare_at_price' => null]);

        $this->assertFalse($product->isOnSale());
    }

    public function test_it_is_not_on_sale_when_compare_at_price_is_not_higher(): void
    {
        $product = $this->makeProduct(['base_price' => 100, 'compare_at_price' => 100]);

        $this->assertFalse($product->isOnSale());
    }

    /* discountPercent ----------------------------------------------------- */

    public function test_it_rounds_the_discount_percentage(): void
    {
        // (150 - 100) / 150 = 33.33% -> rounds to 33
        $product = $this->makeProduct(['base_price' => 100, 'compare_at_price' => 150]);

        $this->assertSame(33, $product->discountPercent());
    }

    public function test_discount_percent_is_null_when_not_on_sale(): void
    {
        $product = $this->makeProduct(['compare_at_price' => null]);

        $this->assertNull($product->discountPercent());
    }

    /* Price labels -------------------------------------------------------- */

    public function test_price_label_formats_the_base_price_in_the_active_locale(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('ر.س 249.90', $this->makeProduct(['base_price' => 249.9])->priceLabel());

        // In Arabic the currency symbol conventionally trails the amount.
        $this->app->setLocale('ar');
        $this->assertSame('249.90 ر.س', $this->makeProduct(['base_price' => 249.9])->priceLabel());
    }

    public function test_compare_at_label_is_only_returned_on_sale(): void
    {
        $this->app->setLocale('en');

        $onSale = $this->makeProduct(['base_price' => 100, 'compare_at_price' => 199.5]);
        $regular = $this->makeProduct(['compare_at_price' => null]);

        $this->assertSame('ر.س 199.50', $onSale->compareAtLabel());
        $this->assertNull($regular->compareAtLabel());
    }

    /* inStock ------------------------------------------------------------- */

    public function test_simple_product_is_in_stock_based_on_quantity(): void
    {
        $this->assertTrue($this->makeProduct(['stock_quantity' => 3])->inStock());
        $this->assertFalse($this->makeProduct(['stock_quantity' => 0])->inStock());
    }

    public function test_variant_product_sums_variant_stock(): void
    {
        $product = $this->makeProduct(['has_variants' => true, 'stock_quantity' => 0]);

        $inStockVariant = new ProductVariant();
        $inStockVariant->forceFill(['stock_quantity' => 4]);

        $product->setRelation('variants', new Collection([$inStockVariant]));

        $this->assertTrue($product->inStock());
    }

    public function test_variant_product_is_out_of_stock_when_all_variants_are_empty(): void
    {
        $product = $this->makeProduct(['has_variants' => true, 'stock_quantity' => 99]);

        $emptyVariant = new ProductVariant();
        $emptyVariant->forceFill(['stock_quantity' => 0]);

        $product->setRelation('variants', new Collection([$emptyVariant]));

        $this->assertFalse($product->inStock());
    }

    /* primaryImageUrl ----------------------------------------------------- */

    public function test_primary_image_falls_back_to_the_placeholder(): void
    {
        $product = $this->makeProduct();
        $product->setRelation('images', new Collection());

        $this->assertStringContainsString('images/placeholder.svg', $product->primaryImageUrl());
    }

    public function test_slug_is_the_route_key(): void
    {
        $this->assertSame('slug', (new Product())->getRouteKeyName());
    }
}
