<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function productWithClosure(bool $required = true): array
    {
        $product = Product::factory()->create([
            'name' => ['en' => 'Noir Abaya', 'ar' => 'عباية سوداء'],
            'base_price' => 500,
            'stock_quantity' => 10,
        ]);

        $option = $product->options()->create([
            'key' => 'closure_style', 'label' => ['ar' => 'الإغلاق', 'en' => 'Closure Style'],
            'type' => 'select', 'is_required' => $required, 'is_active' => true, 'sort_order' => 0,
        ]);
        $open = $option->values()->create(['label' => ['ar' => 'مفتوحة', 'en' => 'Open'], 'price_delta' => 0, 'is_active' => true, 'sort_order' => 0]);
        $closed = $option->values()->create(['label' => ['ar' => 'مغلقة', 'en' => 'Closed'], 'price_delta' => 40, 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);

        return [$product, $option, $open, $closed];
    }

    public function test_add_to_cart_is_blocked_when_a_required_option_is_missing(): void
    {
        [$product] = $this->productWithClosure();

        $this->postJson('/cart', ['product_id' => $product->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('options.'.$product->options->first()->id);

        $this->assertEmpty(session('cart', []));
    }

    public function test_a_value_from_another_product_is_rejected(): void
    {
        [$product, $option] = $this->productWithClosure();
        [$other, $otherOption, $otherOpen] = $this->productWithClosure();

        $this->postJson('/cart', [
            'product_id' => $product->id,
            'options'    => [$option->id => $otherOpen->id],
        ])->assertStatus(422);

        $this->assertEmpty(session('cart', []));
    }

    public function test_a_valid_option_folds_the_price_delta_and_snapshots_the_choice(): void
    {
        [$product, $option, $open, $closed] = $this->productWithClosure();

        $this->postJson('/cart', [
            'product_id' => $product->id,
            'options'    => [$option->id => $closed->id],
        ])->assertOk()->assertJsonPath('item.options.0.value', 'مغلقة'); // request locale is 'ar'

        $row = array_values(session('cart'))[0];
        $this->assertSame(540.0, $row['unit_price']); // 500 + 40
        $this->assertSame('Closed', $row['options'][0]['value_label']['en']);
        $this->assertSame('مغلقة', $row['options'][0]['value_label']['ar']);
        $this->assertSame('closure_style', $row['options'][0]['key']);
    }

    public function test_the_same_product_with_different_options_is_a_separate_row(): void
    {
        [$product, $option, $open, $closed] = $this->productWithClosure();

        $this->post('/cart', ['product_id' => $product->id, 'options' => [$option->id => $open->id]]);
        $this->post('/cart', ['product_id' => $product->id, 'options' => [$option->id => $closed->id]]);

        $this->assertCount(2, session('cart'));
    }

    public function test_the_option_snapshot_flows_to_the_order_item_and_survives_deletion(): void
    {
        $this->app->setLocale('en');
        [$product, $option, $open, $closed] = $this->productWithClosure();

        $cart = new CartService();
        $cart->add($product->id, null, 1, [$option->id => $closed->id]);

        $order = app()->makeWith(CheckoutService::class, ['cart' => $cart])->createOrder(null, [
            'billing_address' => ['recipient_name' => 'Sara', 'phone' => '0500000000'],
            'customer_name'   => 'Sara',
            'customer_email'  => 'sara@example.com',
            'customer_phone'  => '0500000000',
        ]);

        $item = $order->items->first();
        $this->assertSame('Closed', $item->optionLines()['Closure Style']);

        $option->delete();
        $this->assertSame('Closed', $item->fresh()->optionLines()['Closure Style']);
    }

    public function test_an_optional_option_can_be_omitted(): void
    {
        [$product, $option] = $this->productWithClosure(false);

        $this->post('/cart', ['product_id' => $product->id])->assertRedirect();

        $this->assertCount(1, session('cart'));
        $this->assertNull(array_values(session('cart'))[0]['options']);
    }
}
