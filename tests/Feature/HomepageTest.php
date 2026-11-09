<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_renders_ltr_with_brand_tagline(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
        $response->assertSee('lang="en"', false);
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('Awaken your Senses');
        $response->assertSee('bootstrap.min.css', false);
    }

    public function test_arabic_homepage_renders_rtl_with_arabic_content(): void
    {
        $response = $this->get('/ar');

        $response->assertOk();
        $response->assertSee('lang="ar"', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('أيقظ حواسك');
        $response->assertSee('bootstrap.rtl.min.css', false);
    }

    public function test_categories_are_listed_on_the_homepage(): void
    {
        $this->get('/en')->assertSee('Perfumes')->assertSee('Flowers & Gifts');
    }

    public function test_the_offer_banner_shows_the_biggest_discount_still_in_stock(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['base_price' => 760, 'compare_at_price' => 1000]);
        Product::factory()->for($category)->create(['base_price' => 900, 'compare_at_price' => 1000]);
        // A bigger discount, but nothing left to sell at that price.
        Product::factory()->for($category)->outOfStock()->create(['base_price' => 100, 'compare_at_price' => 1000]);

        $this->get('/en')
            ->assertSee('Up to 24% off')
            ->assertSee('on_sale=1', false);
    }

    public function test_there_is_no_offer_banner_when_nothing_is_on_sale(): void
    {
        Product::factory()->for(Category::factory())->create(['base_price' => 760, 'compare_at_price' => null]);

        $this->get('/en')->assertDontSee('on_sale=1', false);
    }

    public function test_the_bestsellers_caption_does_not_depend_on_javascript(): void
    {
        Product::factory()->for(Category::factory())->featured()->create(['name' => ['en' => 'Featured Abaya', 'ar' => 'عباية']]);

        $this->get('/en')->assertSee('aroma-showcase-caption-name">Featured Abaya', false);
    }

    public function test_the_homepage_explains_what_sets_the_abayas_apart(): void
    {
        $this->get('/en')
            ->assertSee(__('storefront.why_aroma.title', [], 'en'))
            ->assertSee(__('storefront.why_aroma.fabric_title', [], 'en'))
            ->assertSee(__('storefront.why_aroma.tailoring_title', [], 'en'));
    }

    public function test_social_icons_without_a_url_are_hidden_instead_of_dead(): void
    {
        config(['aroma.contact.tiktok' => 'https://www.tiktok.com/@aroma', 'aroma.contact.facebook' => '']);

        $this->get('/en')
            ->assertSee('https://www.tiktok.com/@aroma', false)
            ->assertDontSee('bi-facebook', false);
    }

    public function test_registration_numbers_appear_in_the_footer_only_once_set(): void
    {
        config(['aroma.contact.freelance_document' => '', 'aroma.receipts.vat_number' => '']);
        $this->get('/en')->assertDontSee('Freelance Document No.:')->assertDontSee('VAT No.:');

        config(['aroma.contact.freelance_document' => 'FL-123456789', 'aroma.receipts.vat_number' => '300000000000003']);
        $this->get('/en')
            ->assertSee('Freelance Document No.: FL-123456789')
            ->assertSee('VAT No.: 300000000000003');
    }
}
