<?php

namespace Tests\Feature;

use App\Support\Assets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The favicon is also the icon Google Search shows next to the site, and Google is picky:
 * multiples of 48px only (no 16x16), a stable URL, and files that crawlers may fetch.
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int,array{href:string,sizes:?string,rel:string}> */
    private function iconLinks(string $html): array
    {
        preg_match_all('#<link\b[^>]*\brel="(?:shortcut )?(icon|apple-touch-icon)"[^>]*>#i', $html, $tags, PREG_SET_ORDER);

        return array_map(function (array $tag) {
            preg_match('#href="([^"]+)"#', $tag[0], $href);
            preg_match('#sizes="([^"]+)"#', $tag[0], $sizes);

            return ['rel' => $tag[1], 'href' => html_entity_decode($href[1]), 'sizes' => $sizes[1] ?? null];
        }, $tags);
    }

    private function localPath(string $href): string
    {
        return public_path(ltrim((string) parse_url($href, PHP_URL_PATH), '/'));
    }

    public function test_every_storefront_page_advertises_the_same_google_compatible_icons(): void
    {
        $en = $this->iconLinks($this->get('/en')->assertOk()->getContent());
        $ar = $this->iconLinks($this->get('/ar')->assertOk()->getContent());

        $this->assertSame($en, $ar, 'both languages point at the same icon files');

        $icons = array_values(array_filter($en, function ($l) {
            return $l['rel'] === 'icon';
        }));
        $this->assertNotEmpty($icons);

        foreach ($icons as $icon) {
            $this->assertNotNull($icon['sizes'], $icon['href'].' must declare its size');
            [$w, $h] = array_map('intval', explode('x', $icon['sizes']));
            $this->assertSame($w, $h);
            $this->assertSame(0, $w % 48, "{$icon['sizes']} is not a multiple of 48px — Google would ignore or blur it");
        }

        $this->assertNotContains('16x16', array_column($en, 'sizes'), 'Google: do not provide a 16x16 favicon');
        $this->assertNotContains('32x32', array_column($en, 'sizes'));
    }

    public function test_each_advertised_icon_exists_at_the_size_it_declares(): void
    {
        foreach ($this->iconLinks($this->get('/en')->getContent()) as $link) {
            $file = $this->localPath($link['href']);
            $this->assertFileExists($file, $link['href']);

            if (substr($file, -4) === '.png') {
                $info = getimagesize($file);
                $this->assertSame($link['sizes'], $info[0].'x'.$info[1], basename($file).' is not the size the page claims');
            }
        }
    }

    public function test_the_ico_file_contains_a_48px_frame_for_crawlers_that_only_fetch_it(): void
    {
        $ico = file_get_contents(public_path('favicon.ico'));
        [, $type, $count] = array_values(unpack('v3', substr($ico, 0, 6)));
        $this->assertSame(1, $type, 'a real .ico');

        $sizes = [];
        for ($i = 0; $i < $count; $i++) {
            $sizes[] = ord($ico[6 + 16 * $i]) ?: 256;
        }
        $this->assertContains(48, $sizes, 'favicon.ico frames: '.implode(',', $sizes));
    }

    public function test_the_icon_urls_are_stable_across_deploys_and_change_only_with_the_file(): void
    {
        $file = public_path('_favicon-stability.txt');

        try {
            file_put_contents($file, 'one');
            $first = Assets::stable('_favicon-stability.txt');
            $this->assertMatchesRegularExpression('#/_favicon-stability\.txt\?v=[0-9a-f]{8}$#', $first);

            // Same content, newer timestamp (what a deploy that rewrites files does): same URL.
            touch($file, time() + 3600);
            clearstatcache();
            $this->assertSame($first, Assets::stable('_favicon-stability.txt'));
        } finally {
            @unlink($file);
        }

        $this->assertSame(
            asset('favicon.ico').'?v='.substr(md5_file(public_path('favicon.ico')), 0, 8),
            Assets::stable('favicon.ico')
        );
        $this->assertSame(asset('missing-file.png'), Assets::stable('missing-file.png'), 'no version for a file that does not exist');
    }

    public function test_robots_txt_lets_crawlers_fetch_the_icons(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Allow: /', $robots);

        foreach (['favicon', '.png', '.ico', 'android-chrome', 'apple-touch', '/images'] as $blocked) {
            $this->assertStringNotContainsString("Disallow: {$blocked}", $robots);
        }
    }
}
