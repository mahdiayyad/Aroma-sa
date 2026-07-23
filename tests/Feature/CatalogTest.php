<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_page_lists_active_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['name' => ['en' => 'Amber Bloom', 'ar' => 'عنبر']]);

        $this->get('/en/category/'.$category->slug)
            ->assertOk()
            ->assertSee('Amber Bloom');
    }

    public function test_inactive_products_are_hidden(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create([
            'name'      => ['en' => 'Hidden Item', 'ar' => 'مخفي'],
            'is_active' => false,
        ]);

        $this->get('/en/category/'.$category->slug)->assertDontSee('Hidden Item');
    }

    public function test_price_filter_narrows_results(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['name' => ['en' => 'Cheap One', 'ar' => 'رخيص'], 'base_price' => 100]);
        Product::factory()->for($category)->create(['name' => ['en' => 'Pricey One', 'ar' => 'غالي'], 'base_price' => 400]);

        $response = $this->get('/en/category/'.$category->slug.'?price_min=300');

        $response->assertSee('Pricey One')->assertDontSee('Cheap One');
    }

    public function test_product_page_renders_details(): void
    {
        $brand    = Brand::factory()->create(['name' => ['en' => 'Maison Test', 'ar' => 'ميزون']]);
        $category = Category::factory()->create();
        $product  = Product::factory()->for($category)->for($brand)->create([
            'name' => ['en' => 'Signature Scent', 'ar' => 'عطر مميز'],
        ]);

        $this->get('/en/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Signature Scent')
            ->assertSee('Maison Test')
            ->assertSee('Add to cart');
    }

    public function test_unknown_category_and_product_return_404(): void
    {
        $this->get('/en/category/nope')->assertNotFound();
        $this->get('/en/product/nope')->assertNotFound();
    }

    public function test_arabic_product_page_renders_rtl_and_arabic_name(): void
    {
        $category = Category::factory()->create();
        $product  = Product::factory()->for($category)->create([
            'name' => ['en' => 'Rose', 'ar' => 'وردة دمشقية'],
        ]);

        $this->get('/ar/product/'.$product->slug)
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('وردة دمشقية');
    }
}
