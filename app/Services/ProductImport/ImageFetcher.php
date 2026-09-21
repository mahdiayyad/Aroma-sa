<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads a product image from a URL an admin typed into a spreadsheet.
 *
 * That makes this a server-side request forgery surface (a cell containing
 * http://169.254.169.254/… or http://localhost:3306 must never make the
 * server call itself), so it is deliberately strict:
 *
 *  - http/https on ports 80/443 only;
 *  - the host is resolved and EVERY address must be public — private, loopback,
 *    link-local and reserved ranges (IPv4 and IPv6) are refused;
 *  - the connection is pinned to the address that was checked (CURLOPT_RESOLVE),
 *    so a DNS answer that changes between "check" and "connect" can't redirect it;
 *  - redirects are followed by hand, at most N, each hop re-validated;
 *  - a hard time limit and a hard size cap (the transfer is aborted mid-flight);
 *  - the bytes must actually decode as jpeg/png/webp/gif — the Content-Type
 *    header is never trusted, and SVG (script carrier) is refused.
 */
class ImageFetcher
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    /** @var callable(string):array<int,string> */
    private $resolver;

    /** @param callable(string):array<int,string>|null $resolver host -> IP addresses (injectable for tests) */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?: [self::class, 'resolveHost'];
    }

    /** Stable identity of an image URL (scheme/host case, default ports and #fragment ignored). */
    public static function hash(string $url): string
    {
        return sha1(self::normalizeUrl($url));
    }

    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || empty($parts['host'])) {
            return trim($url);
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $port = isset($parts['port']) && ! in_array($parts['port'], [80, 443], true) ? ':'.$parts['port'] : '';

        return $scheme.'://'.strtolower($parts['host']).$port.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** Cheap, network-free check used at validation time. @throws ImageFetchException */
    public function assertAcceptableUrl(string $url): void
    {
        if (mb_strlen($url) > 2048) {
            throw new ImageFetchException('url_too_long');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new ImageFetchException('bad_url', ['url' => $url]);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ImageFetchException('bad_url', ['url' => $url]);
        }

        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        if (! in_array((int) $port, [80, 443], true)) {
            throw new ImageFetchException('bad_port', ['port' => $port]);
        }
    }

    /**
     * @return array{bytes:string,mime:string,ext:string}
     * @throws ImageFetchException
     */
    public function fetch(string $url): array
    {
        $maxBytes = (int) config('aroma.import.image_max_mb', 5) * 1024 * 1024;
        $maxRedirects = (int) config('aroma.import.image_max_redirects', 3);
        $timeout = (int) config('aroma.import.image_timeout', 15);

        $current = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $this->assertAcceptableUrl($current);

            $parts = parse_url($current);
            $host = strtolower(trim((string) $parts['host'], '[]'));
            $port = (int) ($parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80));
            $ip = $this->publicAddressFor($host);

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders(['User-Agent' => 'AromaImporter/1.0', 'Accept' => 'image/jpeg,image/png,image/webp,image/gif'])
                    ->withOptions($this->transferOptions($host, $port, $ip, $maxBytes))
                    ->get($current);
            } catch (ImageFetchException $e) {
                throw $e;
            } catch (Throwable $e) {
                $inner = $e->getPrevious();

                if ($inner instanceof ImageFetchException) {
                    throw $inner;
                }

                // Guzzle wraps an exception thrown from the progress callback.
                if (strpos($e->getMessage(), 'too_large') !== false) {
                    throw new ImageFetchException('too_large', ['max' => (int) config('aroma.import.image_max_mb', 5)]);
                }

                throw new ImageFetchException($e instanceof ConnectionException ? 'unreachable' : 'unreachable', ['reason' => $e->getMessage()]);
            }

            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');

                if ($location === '') {
                    throw new ImageFetchException('http_status', ['status' => $response->status()]);
                }

                $current = $this->absoluteLocation($current, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new ImageFetchException('http_status', ['status' => $response->status()]);
            }

            $declared = (int) $response->header('Content-Length');
            $bytes = $response->body();

            if (($declared > 0 && $declared > $maxBytes) || strlen($bytes) > $maxBytes) {
                throw new ImageFetchException('too_large', ['max' => (int) config('aroma.import.image_max_mb', 5)]);
            }

            return $this->decode($bytes);
        }

        throw new ImageFetchException('too_many_redirects', ['max' => $maxRedirects]);
    }

    /** @return array{bytes:string,mime:string,ext:string} @throws ImageFetchException */
    public function decode(string $bytes): array
    {
        $info = $bytes === '' ? false : @getimagesizefromstring($bytes);

        if ($info === false || empty($info['mime'])) {
            throw new ImageFetchException('not_an_image');
        }

        $allowed = (array) config('aroma.import.image_mimes', array_keys(self::EXTENSIONS));

        if (! in_array($info['mime'], $allowed, true) || ! isset(self::EXTENSIONS[$info['mime']])) {
            throw new ImageFetchException('unsupported_type', ['type' => $info['mime']]);
        }

        return ['bytes' => $bytes, 'mime' => $info['mime'], 'ext' => self::EXTENSIONS[$info['mime']]];
    }

    /** @throws ImageFetchException */
    private function publicAddressFor(string $host): string
    {
        // A literal IP is judged as written — never delegated to a resolver.
        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_values(array_filter((array) call_user_func($this->resolver, $host)));

        if ($addresses === []) {
            throw new ImageFetchException('unresolvable', ['host' => $host]);
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ImageFetchException('private_address', ['host' => $host]);
            }
        }

        // Prefer IPv4 for the pinned connection.
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $address;
            }
        }

        return $addresses[0];
    }

    /** @return array<string,mixed> */
    private function transferOptions(string $host, int $port, string $ip, int $maxBytes): array
    {
        $options = [
            'allow_redirects' => false,
            'http_errors'     => false,
            'connect_timeout' => 10,
            // Abort mid-transfer once the cap is passed instead of buffering a huge body.
            'progress'        => function ($downloadTotal, $downloaded) use ($maxBytes) {
                if ($downloadTotal > $maxBytes || $downloaded > $maxBytes) {
                    throw new ImageFetchException('too_large', ['max' => (int) config('aroma.import.image_max_mb', 5)]);
                }
            },
        ];

        if (defined('CURLOPT_RESOLVE')) {
            // Pin the connection to the address that passed the check.
            $options['curl'] = [CURLOPT_RESOLVE => [$host.':'.$port.':'.$ip]];
        }

        return $options;
    }

    private function absoluteLocation(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (strpos($location, '//') === 0) {
            return $parts['scheme'].':'.$location;
        }

        if (strpos($location, '/') === 0) {
            return $origin.$location;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$location;
    }

    /** @return array<int,string> */
    public static function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $addresses = $v4;
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (! empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return $addresses;
    }
}
