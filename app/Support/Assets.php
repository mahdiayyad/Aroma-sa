<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cache-busted asset URLs.
 *
 * The storefront CSS/JS are hand-authored (no build step), so browsers happily
 * serve a stale copy after a deploy. Appending the file's mtime gives every
 * change a fresh URL without any manual version bumping.
 */
class Assets
{
    public static function versioned(string $path): string
    {
        $full = public_path($path);
        $stamp = is_file($full) ? filemtime($full) : null;

        return asset($path).($stamp ? '?v='.$stamp : '');
    }

    /**
     * URL for a file whose address should stay put — the favicon: Google asks for a
     * stable favicon URL, and an mtime version changes on any deploy that rewrites
     * file timestamps. Versioned by content instead, so the URL changes only when
     * the file really does (browsers then drop their cached copy, crawlers see a
     * deliberate change).
     */
    public static function stable(string $path): string
    {
        static $hashes = [];

        $full = public_path($path);

        if (! array_key_exists($full, $hashes)) {
            $hashes[$full] = is_file($full) ? substr((string) md5_file($full), 0, 8) : null;
        }

        return asset($path).($hashes[$full] ? '?v='.$hashes[$full] : '');
    }
}
