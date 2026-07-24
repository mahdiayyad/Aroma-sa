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

    public function test_ajax_add_to_cart_returns_json_with_the_live_count(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $this->postJson('/cart', ['product_id' => $product->id, 'qty' => 2])
            ->assertOk()
            ->assertJson(['count' => 2])
            ->assertJsonStructure(['count', 'message']);
    }

    public function test_ajax_add_to_cart_returns_validation_errors_as_json(): void
    {
        $this->postJson('/cart', ['product_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_ajax_quantity_update_returns_live_totals(): void
    {
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1]);
        $rowId = md5($product->id.':0');

        $this->patchJson('/cart/'.$rowId, ['qty' => 3])
            ->assertOk()
            ->assertJson(['count' => 3, 'removed' => false])
            ->assertJsonStructure(['count', 'line_total', 'subtotal']);
    }

    public function test_admin_cannot_add_to_cart(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_ADMIN]);
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $this->actingAs($admin)
            ->post('/cart', ['product_id' => $product->id])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertEmpty(session('cart', []));
    }
}
