<?php

namespace Tests\Unit;

use App\Models\ProductVariant;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    private function makeVariant(array $attributes = []): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->forceFill(array_merge(['price' => 120, 'stock_quantity' => 2], $attributes));

        return $variant;
    }

    public function test_it_is_in_stock_when_quantity_is_positive(): void
    {
        $this->assertTrue($this->makeVariant(['stock_quantity' => 1])->inStock());
        $this->assertFalse($this->makeVariant(['stock_quantity' => 0])->inStock());
    }

    public function test_price_label_formats_in_the_active_locale(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('ر.س 120.00', $this->makeVariant(['price' => 120])->priceLabel());

        $this->app->setLocale('ar');
        $this->assertSame('120.00 ر.س', $this->makeVariant(['price' => 120])->priceLabel());
    }
}
