<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "View store" (admin sidebar/topbar) used to be non-functional: BlockAdminShopping
 * bounced any signed-in admin off every storefront page, straight back to /admin.
 * These cover the preview mode that separates browsing from shopping.
 */
class AdminStorePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_STAFF]);
    }

    public function test_view_store_starts_a_preview_and_lands_on_the_home_page(): void
    {
        $this->actingAs($this->admin())->get(route('admin.preview-store'))
            ->assertRedirect(route('home', 'ar')); // config('aroma.default_locale')
    }

    public function test_while_previewing_an_admin_can_browse_the_general_storefront(): void
    {
        $category = Category::factory()->create(['slug' => 'abayas']);
        Product::factory()->create(['category_id' => $category->id, 'slug' => 'preview-me']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.preview-store'));

        foreach (['/en', '/en/category/abayas', '/en/product/preview-me', '/about', '/contact', '/guides'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_the_preview_banner_only_shows_while_previewing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.preview-store'));
        $this->get('/en')->assertOk()->assertSee(__('storefront.preview_banner'))->assertSee(route('admin.exit-preview'), false);
    }

    public function test_shopping_actions_stay_blocked_while_previewing_and_the_admin_stays_on_the_page(): void
    {
        $product = Product::factory()->create(['slug' => 'preview-me']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.preview-store'));

        // Browsing the product page records it as "the page they were on" for back().
        $this->get('/en/product/preview-me')->assertOk();

        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])
            ->assertRedirect('/en/product/preview-me')
            ->assertSessionHas('error', __('storefront.admin_no_shop'));
        $this->assertSame([], session('cart', []), 'nothing was added to the cart');

        foreach (['/account', '/cart', '/checkout/review', '/orders'] as $blocked) {
            $this->get($blocked)->assertRedirect(); // sent back, not straight to /admin
        }

        // Still previewing — a blocked action doesn't end the preview.
        $this->get('/en')->assertOk()->assertSee(__('storefront.preview_banner'));
    }

    public function test_exit_preview_clears_it_and_storefront_pages_bounce_to_admin_again(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.preview-store'));
        $this->get('/en')->assertOk();

        $this->get(route('admin.exit-preview'))->assertRedirect(route('admin.dashboard'));

        $this->get('/en')->assertRedirect(route('admin.dashboard'));
        $this->get('/en')->assertDontSee(__('storefront.preview_banner'));
    }

    public function test_without_ever_previewing_storefront_pages_still_bounce_straight_to_admin(): void
    {
        $this->actingAs($this->admin())->get('/en')->assertRedirect(route('admin.dashboard'));
    }

    public function test_staff_get_the_same_preview_as_admin(): void
    {
        $this->actingAs($this->staff())->get(route('admin.preview-store'))->assertRedirect(route('home', 'ar'));
        $this->get('/en')->assertOk()->assertSee(__('storefront.preview_banner'));
        $this->get('/cart')->assertRedirect(); // still no shopping
    }

    public function test_a_shopper_never_sees_the_banner_and_the_preview_flag_is_never_touched_for_them(): void
    {
        $shopper = User::factory()->create();

        $this->actingAs($shopper)->get('/en')->assertOk()->assertDontSee(__('storefront.preview_banner'));
        $this->assertNull(session('admin_store_preview'));

        // A shopper hitting the admin-only preview route at all is simply refused entry.
        $this->get(route('admin.preview-store'))->assertForbidden();
    }

    public function test_a_guest_is_unaffected(): void
    {
        $this->get('/en')->assertOk()->assertDontSee(__('storefront.preview_banner'));
    }
}
