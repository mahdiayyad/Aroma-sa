<?php

namespace Tests\Feature;

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
}
