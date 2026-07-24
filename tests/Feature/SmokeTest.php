<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every primary storefront and account page with realistic data and
 * asserts none of them 500. This is the broad "does the whole system load"
 * safety net; deeper behaviour lives in the focused feature tests.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): Product
    {
        $category = Category::factory()->create(['slug' => 'perfumes']);

        return Product::factory()->create([
            'category_id'    => $category->id,
            'slug'           => 'amber-nights',
            'stock_quantity' => 10,
            'is_active'      => true,
        ]);
    }

    private function makeOrderFor(User $user): Order
    {
        $address = [
            'recipient_name' => $user->name,
            'phone'          => '0500000000',
            'street_address' => 'King Fahd Rd',
            'city'           => 'Riyadh',
            'region'         => 'Riyadh',
            'postal_code'    => '12211',
        ];

        return Order::create([
            'user_id'          => $user->id,
            'order_number'     => 'AR-2026-000123',
            'status'           => Order::STATUS_PAID,
            'customer_name'    => $user->name,
            'customer_email'   => $user->email,
            'customer_phone'   => '0500000000',
            'billing_address'  => $address,
            'shipping_address' => $address,
            'subtotal'         => 200,
            'total_amount'     => 200,
        ]);
    }

    /** @return array<string,string> route name => expected non-error */
    public function test_guest_storefront_pages_load(): void
    {
        $product = $this->seedCatalog();

        foreach (['ar', 'en'] as $locale) {
            $this->get(route('home', $locale))->assertOk();
            $this->get(route('category.show', [$locale, 'perfumes']))->assertOk();
            $this->get(route('product.show', [$locale, $product->slug]))->assertOk();
        }

        $this->get(route('cart.index'))->assertOk();
        $this->get(route('terms'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_guest_checkout_entry_pages_load(): void
    {
        $product = $this->seedCatalog();
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1]);

        $this->get(route('checkout.review'))->assertOk();
        $this->get(route('checkout.start'))->assertOk();   // auth-choice screen
        $this->get(route('checkout.address'))->assertOk();
    }

    public function test_authenticated_account_pages_load(): void
    {
        $user = User::factory()->create();
        $this->makeOrderFor($user);

        $this->actingAs($user);

        $this->get(route('account.dashboard'))->assertOk();
        $this->get(route('account.profile.edit'))->assertOk();
        $this->get(route('wishlist.index'))->assertOk();
        $this->get(route('order.index'))->assertOk();
        $this->get(route('order.show', Order::first()))->assertOk();
    }
}
