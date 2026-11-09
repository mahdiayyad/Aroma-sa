<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPixelsTest extends TestCase
{
    use RefreshDatabase;

    private const META = 'meta-pixel-test-id';
    private const TIKTOK = 'tiktok-pixel-test-id';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aroma.marketing.meta_pixel_id' => self::META,
            'aroma.marketing.tiktok_pixel_id' => self::TIKTOK,
        ]);
    }

    private function inProduction(): void
    {
        app()->instance('env', 'production');
    }

    private function assertPixelsPresent(string $html): void
    {
        $this->assertStringContainsString('ttq.load("'.self::TIKTOK.'")', $html);
        $this->assertStringContainsString('fbq(\'init\', "'.self::META.'")', $html);
        $this->assertStringContainsString('https://www.facebook.com/tr?id='.self::META, $html);

        // Vendor guidance: scripts in the head, and the no-JS image in the body.
        $headEnd = strpos($html, '</head>');
        $this->assertLessThan($headEnd, strpos($html, 'ttq.load('));
        $this->assertLessThan($headEnd, strpos($html, "fbq('init'"));
        $this->assertGreaterThan($headEnd, strpos($html, 'facebook.com/tr?id='));
    }

    private function assertPixelsAbsent(string $html): void
    {
        $this->assertStringNotContainsString('ttq.load(', $html);
        $this->assertStringNotContainsString("fbq('init'", $html);
        $this->assertStringNotContainsString('facebook.com/tr?id=', $html);
    }

    public function test_storefront_pages_load_both_pixels_in_production(): void
    {
        $this->inProduction();

        $this->assertPixelsPresent($this->get('/en')->assertOk()->getContent());
    }

    public function test_sign_in_pages_load_both_pixels_in_production(): void
    {
        $this->inProduction();

        $this->assertPixelsPresent($this->get(route('login'))->assertOk()->getContent());
    }

    public function test_pixels_do_not_load_outside_production(): void
    {
        $this->assertPixelsAbsent($this->get('/en')->assertOk()->getContent());
    }

    public function test_staff_previewing_the_store_are_not_tracked(): void
    {
        $this->inProduction();

        $html = $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]))
            ->withSession(['admin_store_preview' => true])
            ->get('/en')
            ->assertOk()
            ->getContent();

        $this->assertPixelsAbsent($html);
    }

    public function test_signed_in_customers_are_tracked(): void
    {
        $this->inProduction();

        $html = $this->actingAs(User::factory()->create())->get('/en')->assertOk()->getContent();

        $this->assertPixelsPresent($html);
    }

    public function test_the_admin_panel_never_loads_the_pixels(): void
    {
        $this->inProduction();

        $this->assertPixelsAbsent($this->get(route('admin.login'))->assertOk()->getContent());
    }

    public function test_a_blank_id_turns_that_pixel_off(): void
    {
        $this->inProduction();
        config(['aroma.marketing.tiktok_pixel_id' => '']);

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringNotContainsString('ttq.load(', $html);
        $this->assertStringContainsString("fbq('init'", $html);
    }
}
