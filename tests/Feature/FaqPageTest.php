<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_faq_page_answers_with_the_same_delivery_times_checkout_shows(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee(__('delivery.notice'))
            ->assertSee(__('checkout.shipping_days.international'));
    }

    public function test_the_faq_page_is_linked_from_the_footer_and_listed_in_the_sitemap(): void
    {
        $this->get('/en')->assertSee('href="'.route('faq').'"', false);
        $this->get(route('sitemap'))->assertSee(route('faq'), false);
    }
}
