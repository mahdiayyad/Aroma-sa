<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin product-edit "media" card previously had no way to remove a
 * single uploaded image (only add more). Covers the new AJAX delete route.
 */
class AdminProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function imageOn(Product $product, array $overrides = [])
    {
        return $product->images()->create(array_merge([
            'disk' => 'public',
            'path' => 'products/'.uniqid().'.jpg',
            'is_primary' => false,
            'sort_order' => 0,
        ], $overrides));
    }

    public function test_admin_can_remove_a_non_primary_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $primary = $this->imageOn($product, ['is_primary' => true, 'sort_order' => 0]);
        $extra = $this->imageOn($product, ['is_primary' => false, 'sort_order' => 1]);
        Storage::disk('public')->put($extra->path, 'fake-image-bytes');

        $this->actingAs($this->admin())
            ->deleteJson(route('admin.products.images.destroy', [$product, $extra]))
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('product_images', ['id' => $extra->id]);
        Storage::disk('public')->assertMissing($extra->path);

        // The untouched primary image is unaffected.
        $this->assertDatabaseHas('product_images', ['id' => $primary->id, 'is_primary' => true]);
    }

    public function test_deleting_the_primary_image_promotes_the_next_one(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $primary = $this->imageOn($product, ['is_primary' => true, 'sort_order' => 0]);
        $second = $this->imageOn($product, ['is_primary' => false, 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->deleteJson(route('admin.products.images.destroy', [$product, $primary]))
            ->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $primary->id]);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_deleting_the_only_image_leaves_the_product_with_none(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $only = $this->imageOn($product, ['is_primary' => true]);

        $this->actingAs($this->admin())
            ->deleteJson(route('admin.products.images.destroy', [$product, $only]))
            ->assertOk();

        $this->assertSame(0, $product->images()->count());
        // Falls back to the placeholder rather than erroring.
        $this->assertStringContainsString('placeholder', $product->fresh()->primaryImageUrl());
    }

    public function test_a_root_relative_placeholder_path_is_not_sent_to_storage(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $placeholder = $this->imageOn($product, ['path' => '/images/placeholder.svg', 'is_primary' => true]);

        $this->actingAs($this->admin())
            ->deleteJson(route('admin.products.images.destroy', [$product, $placeholder]))
            ->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $placeholder->id]);
    }

    public function test_an_image_belonging_to_another_product_cannot_be_deleted_through_this_product(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $image = $this->imageOn($other, ['is_primary' => true]);

        $this->actingAs($this->admin())
            ->deleteJson(route('admin.products.images.destroy', [$product, $image]))
            ->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    public function test_a_non_admin_cannot_delete_a_product_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $image = $this->imageOn($product, ['is_primary' => true]);

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('admin.products.images.destroy', [$product, $image]))
            ->assertForbidden();

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    public function test_the_edit_page_renders_a_remove_control_per_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $image = $this->imageOn($product, ['is_primary' => true]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('js-delete-image', $html);
        $this->assertStringContainsString(route('admin.products.images.destroy', [$product, $image]), $html);
    }
}
