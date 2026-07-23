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
}
