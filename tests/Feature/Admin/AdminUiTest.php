<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makeOrder(string $status = Order::STATUS_PENDING): Order
    {
        $address = ['recipient_name' => 'Sara', 'phone' => '0500000000', 'street_address' => 'K', 'city' => 'Riyadh', 'region' => 'Riyadh', 'postal_code' => ''];

        return Order::create([
            'order_number' => 'AR-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => $status, 'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => $address, 'shipping_address' => $address, 'subtotal' => 200, 'total_amount' => 200,
        ]);
    }

    /* Access control ------------------------------------------------------- */

    public function test_login_page_renders(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Aroma');
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_a_customer_is_forbidden_from_the_back_office(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_the_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/admin')
            ->assertOk()
            ->assertSee(__('admin.dashboard.revenue'));
    }

    /* Login flow ----------------------------------------------------------- */

    public function test_admin_can_sign_in(): void
    {
        $admin = User::factory()->create(['email' => 'a@x.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN]);

        $this->post(route('admin.login.attempt'), ['email' => 'a@x.com', 'password' => 'secret123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_customer_cannot_sign_into_admin(): void
    {
        User::factory()->create(['email' => 'c@x.com', 'password' => Hash::make('secret123')]);

        $this->post(route('admin.login.attempt'), ['email' => 'c@x.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /* Admins are confined to the back-office --------------------------------- */

    public function test_an_admin_is_sent_back_to_the_dashboard_from_storefront_pages(): void
    {
        $product = Product::factory()->create(['slug' => 'shop-me']);
        $admin   = $this->admin();

        foreach (['/ar', '/en', '/en/product/shop-me', '/cart', '/account', '/checkout/review'] as $path) {
            $this->actingAs($admin)->get($path)
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    public function test_an_admin_can_still_reach_the_back_office_and_sign_out(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/products')->assertOk();
        $this->actingAs($admin)->get('/locale/en')->assertRedirect();      // language switch
        $this->actingAs($admin)->get('/csrf-token')->assertOk();           // token refresh
        $this->actingAs($admin)->post('/logout')->assertRedirect();        // sign out
    }

    public function test_a_shopper_is_unaffected_by_the_admin_confinement(): void
    {
        $shopper = User::factory()->create(); // role: customer

        $this->actingAs($shopper)->get('/ar')->assertOk();
        $this->actingAs($shopper)->get('/cart')->assertOk();
    }

    /* Catalog CRUD --------------------------------------------------------- */

    public function test_admin_can_open_product_screens(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.edit', Product::factory()->create()))->assertOk();
    }

    public function test_admin_can_create_a_product(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'name' => ['ar' => 'ورد', 'en' => 'Rose'],
            'category_id' => $category->id,
            'base_price' => 200,
            'stock_quantity' => 5,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseCount('products', 1);
        $this->assertSame('Rose', Product::first()->getTranslations('name')['en']);
    }

    public function test_admin_can_create_and_delete_a_category(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => ['ar' => 'عطور', 'en' => 'Perfumes'],
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseCount('categories', 1);
        $category = Category::first();

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_with_products_is_not_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    /* Orders --------------------------------------------------------------- */

    public function test_admin_can_advance_an_order_status(): void
    {
        $order = $this->makeOrder(Order::STATUS_PENDING);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_PAID])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_admin_cannot_apply_an_illegal_status(): void
    {
        $order = $this->makeOrder(Order::STATUS_PENDING);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_DELIVERED])
            ->assertSessionHas('error');

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }
}
