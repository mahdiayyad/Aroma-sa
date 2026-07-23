<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_a_product_to_cart(): void
    {
        $product = Product::factory()->create([
            'name'           => ['en' => 'Amber Nights', 'ar' => 'ليالي العنبر'],
            'stock_quantity' => 5,
        ]);

        $this->post('/cart', ['product_id' => $product->id])->assertRedirect();

        // Non-prefixed cart route renders in the default locale (Arabic).
        $this->get('/cart')->assertOk()->assertSee('ليالي العنبر');
    }

    public function test_adding_invalid_product_fails_validation(): void
    {
        $this->post('/cart', ['product_id' => 999999])->assertSessionHasErrors('product_id');
    }

    public function test_cart_quantity_can_be_updated_and_removed(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $rowId   = md5($product->id.':0');

        $this->post('/cart', ['product_id' => $product->id, 'qty' => 2]);
        $this->assertSame(2, array_sum(array_column(session('cart'), 'qty')));

        $this->patch('/cart/'.$rowId, ['qty' => 4]);
        $this->assertSame(4, array_sum(array_column(session('cart'), 'qty')));

        $this->delete('/cart/'.$rowId);
        $this->assertEmpty(session('cart', []));
    }

    public function test_cart_stock_is_capped_at_available_quantity(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->post('/cart', ['product_id' => $product->id, 'qty' => 10]);

        $this->assertSame(3, array_sum(array_column(session('cart'), 'qty')));
    }
}
