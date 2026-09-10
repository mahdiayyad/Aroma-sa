<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductOptionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'label'         => ['ar' => 'نوع الإغلاق', 'en' => 'Closure Style'],
            'key'           => 'closure_style',
            'is_required'   => '1',
            'is_active'     => '1',
            'sort_order'    => 0,
            'default_index' => '1',
            'values'        => [
                ['label' => ['ar' => 'مفتوحة', 'en' => 'Open'], 'price_delta' => '0', 'is_active' => '1', 'sort_order' => 0],
                ['label' => ['ar' => 'مغلقة', 'en' => 'Closed'], 'price_delta' => '25', 'is_active' => '1', 'sort_order' => 1],
            ],
        ], $overrides);
    }

    public function test_admin_can_open_the_option_screens(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();

        $this->actingAs($admin)->get(route('admin.products.options.index', $product))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.options.create', $product))->assertOk();
    }

    public function test_admin_can_create_an_option_with_values(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.products.options.store', $product), $this->validPayload())
            ->assertRedirect(route('admin.products.options.index', $product));

        $option = ProductOption::first();
        $this->assertSame('closure_style', $option->key);
        $this->assertSame('Closure Style', $option->getTranslations('label')['en']);
        $this->assertSame('نوع الإغلاق', $option->getTranslations('label')['ar']);
        $this->assertTrue($option->is_required);
        $this->assertCount(2, $option->values);

        $default = $option->values->firstWhere('is_default', true);
        $this->assertSame('Closed', $default->getTranslations('label')['en']);
        $this->assertEquals(25.0, (float) $default->price_delta);
    }

    public function test_key_is_generated_from_the_english_label_when_blank(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.products.options.store', $product), $this->validPayload(['key' => '']))
            ->assertRedirect();

        $this->assertSame('closure_style', ProductOption::first()->key);
    }

    public function test_an_option_with_no_active_value_is_rejected(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.products.options.store', $product), $this->validPayload([
                'values' => [
                    ['label' => ['ar' => 'أ', 'en' => 'A'], 'price_delta' => '0', 'is_active' => '0', 'sort_order' => 0],
                ],
            ]))
            ->assertSessionHasErrors('values');

        $this->assertDatabaseCount('product_options', 0);
    }

    public function test_admin_can_edit_an_option_and_values_are_replaced(): void
    {
        $product = Product::factory()->create();
        $option = $product->options()->create([
            'key' => 'closure_style', 'label' => ['ar' => 'إغلاق', 'en' => 'Closure'], 'type' => 'select',
            'is_required' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $option->values()->create(['label' => ['ar' => 'قديم', 'en' => 'Old'], 'price_delta' => 0, 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs($this->admin())
            ->put(route('admin.options.update', $option), $this->validPayload())
            ->assertRedirect(route('admin.products.options.index', $product));

        $option->refresh()->load('values');
        $this->assertCount(2, $option->values);
        $this->assertNull($option->values->firstWhere('label->en', 'Old'));
    }

    public function test_admin_can_delete_an_option(): void
    {
        $product = Product::factory()->create();
        $option = $product->options()->create([
            'key' => 'x', 'label' => ['ar' => 'x', 'en' => 'x'], 'type' => 'select',
            'is_required' => false, 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.options.destroy', $option))
            ->assertRedirect(route('admin.products.options.index', $product));

        $this->assertDatabaseMissing('product_options', ['id' => $option->id]);
    }

    public function test_a_customer_cannot_reach_the_option_admin(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.products.options.index', $product))
            ->assertForbidden();
    }
}
