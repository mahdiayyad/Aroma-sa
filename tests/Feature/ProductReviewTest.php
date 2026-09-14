<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    /* ---- Creating reviews --------------------------------------------------- */

    public function test_an_authenticated_customer_can_submit_a_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 5,
            'body' => 'This is my new favourite fragrance, lasts all day.',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('product_reviews', [
            'user_id' => $user->id, 'product_id' => $product->id, 'rating' => 5,
        ]);
    }

    public function test_a_guest_cannot_submit_a_review(): void
    {
        $product = Product::factory()->create();

        $this->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Great!'])
            ->assertRedirect(route('login'));
    }

    /* ---- The critical bug: a submitted review must appear immediately -------- */

    public function test_a_submitted_review_is_visible_on_the_product_page_right_away(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // A real visit to the PDP first, matching how a shopper actually
        // gets to the review form, so the controller's back() redirect
        // (Referer-based) genuinely lands back on this same product page.
        $this->actingAs($user)->get(route('product.show', ['ar', $product->slug]));

        $response = $this->actingAs($user)->from(route('product.show', ['ar', $product->slug]))
            ->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Appears immediately, no approval wait']);

        $response->assertRedirect(route('product.show', ['ar', $product->slug]));
        $this->assertDatabaseHas('product_reviews', ['user_id' => $user->id, 'product_id' => $product->id, 'status' => 'approved']);

        // Follow that redirect in the same session — exactly what a real
        // browser does after a form POST, no manual refresh involved.
        $this->get($response->headers->get('Location'))->assertSee('Appears immediately, no approval wait');
    }

    public function test_the_product_review_count_and_average_update_on_submission(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['reviews_count' => 0, 'reviews_avg_rating' => 0]);

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 4, 'body' => 'Solid product']);

        $this->assertEquals(1, $product->fresh()->reviews_count);
        $this->assertEquals(4.0, (float) $product->fresh()->reviews_avg_rating);
    }

    public function test_a_review_remains_visible_after_the_page_is_reloaded(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Still here after reload']);

        // A brand new, unauthenticated request — simulates hitting refresh.
        $this->get(route('product.show', ['ar', $product->slug]))->assertSee('Still here after reload');
    }

    /* ---- Invalid ratings ------------------------------------------------------ */

    public function test_a_rating_outside_1_to_5_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 6, 'body' => 'Text'])
            ->assertSessionHasErrors('rating');

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 0, 'body' => 'Text'])
            ->assertSessionHasErrors('rating');
    }

    public function test_a_rating_is_required(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $product), ['body' => 'No rating given'])
            ->assertSessionHasErrors('rating');
    }

    public function test_a_review_requires_comment_text(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 4])
            ->assertSessionHasErrors('body');
    }

    public function test_a_comment_over_1000_characters_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 4, 'body' => str_repeat('a', 1001),
        ])->assertSessionHasErrors('body');
    }

    /* ---- Duplicate review attempts --------------------------------------------- */

    public function test_a_customer_cannot_review_the_same_product_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        ProductReview::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 3, 'body' => 'Second attempt'])
            ->assertSessionHasErrors('review');

        $this->assertEquals(1, ProductReview::where('user_id', $user->id)->where('product_id', $product->id)->count());
    }

    /* ---- Verified purchase detection ------------------------------------------- */

    public function test_a_review_is_flagged_verified_when_the_customer_actually_bought_it(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => Order::STATUS_DELIVERED]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_data' => ['name' => 'X', 'sku' => 'X'], 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100,
        ]);

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Loved it']);

        $this->assertDatabaseHas('product_reviews', ['user_id' => $user->id, 'product_id' => $product->id, 'is_verified_purchase' => 1]);
    }

    public function test_a_review_is_not_verified_without_a_real_purchase(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Looks nice']);

        $this->assertDatabaseHas('product_reviews', ['user_id' => $user->id, 'product_id' => $product->id, 'is_verified_purchase' => 0]);
    }

    public function test_an_unpaid_order_does_not_count_as_a_verified_purchase(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => Order::STATUS_PENDING]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_data' => ['name' => 'X', 'sku' => 'X'], 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100,
        ]);

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Nice']);

        $this->assertDatabaseHas('product_reviews', ['user_id' => $user->id, 'product_id' => $product->id, 'is_verified_purchase' => 0]);
    }

    /* ---- Admin moderation still works (post-submission, not pre-approval) ----- */

    public function test_admin_can_hide_an_approved_review(): void
    {
        $product = Product::factory()->create();
        $review = ProductReview::factory()->create(['product_id' => $product->id, 'rating' => 5]);
        app(ReviewService::class)->approve($review); // establish the baseline count/avg like submit() would
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->patch(route('admin.reviews.hide', $review))->assertRedirect();

        $this->assertSame('hidden', $review->fresh()->status);
        $this->assertEquals(0, $product->fresh()->reviews_count);

        auth()->logout();
        $this->get(route('product.show', ['ar', $product->slug]))->assertDontSee($review->body);
    }

    public function test_admin_can_delete_a_review(): void
    {
        $review = ProductReview::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->delete(route('admin.reviews.destroy', $review))->assertRedirect();

        $this->assertSoftDeleted('product_reviews', ['id' => $review->id]);
    }
}
