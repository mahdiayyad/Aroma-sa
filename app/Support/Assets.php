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
}
