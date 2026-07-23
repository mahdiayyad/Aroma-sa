<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_wishlist(): void
    {
        $this->get('/account/wishlist')->assertRedirect('/login');
    }

    public function test_user_can_toggle_a_product_in_wishlist(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create();

        // Add
        $this->actingAs($user)->post(route('wishlist.toggle', $product->slug))->assertRedirect();
        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);

        // Toggle off
        $this->actingAs($user)->post(route('wishlist.toggle', $product->slug));
        $this->assertDatabaseMissing('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_wishlist_index_lists_saved_products(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['name' => ['en' => 'Saved Scent', 'ar' => 'عطر محفوظ']]);
        $user->wishlistItems()->create(['product_id' => $product->id]);

        // Non-prefixed account routes render in the default locale (Arabic).
        $this->actingAs($user)->get('/account/wishlist')->assertOk()->assertSee('عطر محفوظ');
    }
}
