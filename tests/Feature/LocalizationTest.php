<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_root_redirects_to_the_default_locale(): void
    {
        $this->get('/')->assertRedirect('/ar');
    }

    public function test_unsupported_locale_prefix_returns_not_found(): void
    {
        // The {locale} route is constrained to ar|en, so /fr must 404.
        $this->get('/fr')->assertNotFound();
    }

    public function test_locale_switch_persists_choice_in_session(): void
    {
        $this->get('/locale/en')->assertRedirect();
        $this->assertSame('en', session('locale'));
    }

    public function test_switching_language_on_a_prefixed_page_swaps_the_url_prefix(): void
    {
        // Coming from /en/product/rose, switching to Arabic must land on
        // /ar/product/rose (otherwise the prefix keeps the page English).
        $this->get('/locale/ar', ['referer' => url('/en/product/rose')])
            ->assertRedirect(url('/ar/product/rose'));

        $this->assertSame('ar', session('locale'));
    }

    public function test_switching_language_keeps_the_query_string(): void
    {
        $this->get('/locale/ar', ['referer' => url('/en/category/perfumes?sort=newest')])
            ->assertRedirect(url('/ar/category/perfumes').'?sort=newest');
    }

    public function test_switching_on_a_non_prefixed_page_returns_to_it(): void
    {
        // Cart has no locale prefix — the session carries the choice.
        $this->get('/locale/en', ['referer' => url('/cart')])
            ->assertRedirect(url('/cart'));

        $this->assertSame('en', session('locale'));
    }

    public function test_a_switched_prefixed_page_renders_in_the_new_language(): void
    {
        // The Arabic homepage renders RTL; proves the swap actually takes effect.
        $this->get('/ar')->assertOk()->assertSee('dir="rtl"', false);
        $this->get('/en')->assertOk()->assertSee('dir="ltr"', false);
    }
}
