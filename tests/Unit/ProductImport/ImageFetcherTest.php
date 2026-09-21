<?php

namespace Tests\Unit\ProductImport;

use App\Services\ProductImport\ImageFetchException;
use App\Services\ProductImport\ImageFetcher;
use Tests\TestCase;

class ImageFetcherTest extends TestCase
{
    private function fetcher(): ImageFetcher
    {
        // Never touches DNS: every name "resolves" to a public address.
        return new ImageFetcher(function () {
            return ['93.184.216.34'];
        });
    }

    /** @dataProvider sameImage */
    public function test_urls_that_differ_only_cosmetically_share_one_identity(string $a, string $b): void
    {
        $this->assertSame(ImageFetcher::hash($a), ImageFetcher::hash($b));
    }

    public function sameImage(): array
    {
        return [
            'host case'        => ['https://CDN.Example.com/a.jpg', 'https://cdn.example.com/a.jpg'],
            'default port'     => ['https://cdn.example.com:443/a.jpg', 'https://cdn.example.com/a.jpg'],
            'fragment'         => ['https://cdn.example.com/a.jpg#top', 'https://cdn.example.com/a.jpg'],
            'surrounding space' => ['  https://cdn.example.com/a.jpg ', 'https://cdn.example.com/a.jpg'],
        ];
    }

    public function test_different_paths_queries_and_ports_are_different_images(): void
    {
        $base = ImageFetcher::hash('https://cdn.example.com/a.jpg');

        $this->assertNotSame($base, ImageFetcher::hash('https://cdn.example.com/b.jpg'));
        $this->assertNotSame($base, ImageFetcher::hash('https://cdn.example.com/a.jpg?v=2'));
        $this->assertNotSame($base, ImageFetcher::hash('https://cdn.example.com:8443/a.jpg'));
    }

    /** @dataProvider refused */
    public function test_urls_that_can_never_be_downloaded_are_refused_before_any_network_call(string $url, string $errorKey): void
    {
        try {
            $this->fetcher()->assertAcceptableUrl($url);
            $this->fail("'{$url}' should be refused");
        } catch (ImageFetchException $e) {
            $this->assertSame($errorKey, $e->errorKey);
        }
    }

    public function refused(): array
    {
        return [
            'ftp'              => ['ftp://example.com/a.jpg', 'bad_url'],
            'file'             => ['file:///etc/passwd', 'bad_url'],
            'javascript'       => ['javascript:alert(1)', 'bad_url'],
            'no host'          => ['https:///a.jpg', 'bad_url'],
            'not a url'        => ['just some words', 'bad_url'],
            'credentials'      => ['https://user:pass@example.com/a.jpg', 'bad_url'],
            'odd port'         => ['http://example.com:8080/a.jpg', 'bad_port'],
            'ssh port'         => ['http://example.com:22/a.jpg', 'bad_port'],
            'too long'         => ['https://example.com/'.str_repeat('a', 2100), 'url_too_long'],
        ];
    }

    public function test_ordinary_http_and_https_urls_pass_the_cheap_check(): void
    {
        foreach (['http://example.com/a.jpg', 'https://example.com/a.jpg', 'https://example.com:443/a.jpg', 'http://example.com:80/a.jpg?x=1'] as $url) {
            $this->fetcher()->assertAcceptableUrl($url);
        }

        $this->addToAssertionCount(1);
    }

    /** @dataProvider privateHosts */
    public function test_addresses_on_private_networks_are_never_fetched(string $url): void
    {
        try {
            $this->fetcher()->fetch($url);
            $this->fail("'{$url}' must not be fetched");
        } catch (ImageFetchException $e) {
            $this->assertSame('private_address', $e->errorKey);
        }
    }

    public function privateHosts(): array
    {
        return [
            'loopback'        => ['http://127.0.0.1/a.jpg'],
            'loopback ipv6'   => ['http://[::1]/a.jpg'],
            'private 10/8'    => ['http://10.0.0.5/a.jpg'],
            'private 192.168' => ['http://192.168.1.10/a.jpg'],
            'private 172.16'  => ['http://172.16.0.1/a.jpg'],
            'cloud metadata'  => ['http://169.254.169.254/latest/meta-data/'],
            'unspecified'     => ['http://0.0.0.0/a.jpg'],
        ];
    }

    public function test_a_hostname_that_resolves_to_a_private_address_is_refused_too(): void
    {
        $fetcher = new ImageFetcher(function () {
            return ['93.184.216.34', '10.1.2.3']; // one public, one private: still refused
        });

        $this->expectException(ImageFetchException::class);
        $fetcher->fetch('http://sneaky.example.com/a.jpg');
    }

    public function test_a_hostname_that_does_not_resolve_is_refused(): void
    {
        $fetcher = new ImageFetcher(function () {
            return [];
        });

        try {
            $fetcher->fetch('http://nope.invalid/a.jpg');
            $this->fail('should not fetch');
        } catch (ImageFetchException $e) {
            $this->assertSame('unresolvable', $e->errorKey);
        }
    }

    public function test_decode_accepts_real_images_and_reports_their_type(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $decoded = $this->fetcher()->decode($png);

        $this->assertSame('image/png', $decoded['mime']);
        $this->assertSame('png', $decoded['ext']);
        $this->assertSame($png, $decoded['bytes']);
    }

    /** @dataProvider notImages */
    public function test_decode_refuses_anything_that_is_not_a_raster_image(string $bytes, string $errorKey): void
    {
        try {
            $this->fetcher()->decode($bytes);
            $this->fail('should be refused');
        } catch (ImageFetchException $e) {
            $this->assertSame($errorKey, $e->errorKey);
        }
    }

    public function notImages(): array
    {
        return [
            'empty'      => ['', 'not_an_image'],
            'html'       => ['<html><body>404</body></html>', 'not_an_image'],
            'php'        => ['<?php system($_GET["c"]);', 'not_an_image'],
            // Scriptable and not decodable by getimagesize: never stored as a product photo.
            'svg'        => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'not_an_image'],
        ];
    }
}
