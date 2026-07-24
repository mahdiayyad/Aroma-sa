<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    }

    public function test_create_category_stores_both_locales_and_a_slug(): void
    {
        $this->postJson('/api/admin/categories', [
            'name'      => ['ar' => 'عطور', 'en' => 'Perfumes'],
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name.en', 'Perfumes')
            ->assertJsonPath('data.name.ar', 'عطور')
            ->assertJsonPath('data.slug', 'perfumes');

        $this->assertSame('عطور', Category::first()->getTranslations('name')['ar']);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->deleteJson('/api/admin/categories/'.$category->slug)->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_empty_category_is_deleted(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson('/api/admin/categories/'.$category->slug)->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_create_product_with_bilingual_name(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/admin/products', [
            'name'           => ['ar' => 'ورد', 'en' => 'Rose'],
            'category_id'    => $category->id,
            'base_price'     => 250,
            'stock_quantity' => 8,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name.en', 'Rose')
            ->assertJsonPath('data.base_price', 250)
            ->assertJsonPath('data.slug', 'rose');
    }

    public function test_product_delete_is_soft(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson('/api/admin/products/'.$product->slug)->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_products_list_is_paginated(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'base_price']], 'links', 'meta']);
    }

    public function test_invalid_product_is_rejected(): void
    {
        $this->postJson('/api/admin/products', ['base_price' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name.en', 'category_id', 'base_price', 'stock_quantity']);
    }
}
