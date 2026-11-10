<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Which marketing tags the current page should load. Production only, so local
 * and staging traffic never reaches the ad accounts, and never for signed-in
 * staff or admins (they can browse the store through "View store").
 */
class MarketingPixels
{
    public static function enabled(): bool
    {
        $user = auth()->user();

        return app()->environment('production') && ! ($user && $user->isAdmin());
    }

    /** @return array{meta:?string,tiktok:?string,gtm:?string} */
    public static function ids(): array
    {
        if (! self::enabled()) {
            return ['meta' => null, 'tiktok' => null, 'gtm' => null];
        }

        $gtm = strtoupper(trim((string) config('aroma.marketing.gtm_container_id')));

        return [
            'meta' => config('aroma.marketing.meta_pixel_id') ?: null,
            'tiktok' => config('aroma.marketing.tiktok_pixel_id') ?: null,
            // Only a well-formed container id is ever printed into the page.
            'gtm' => preg_match('/^GTM-[A-Z0-9]+$/', $gtm) === 1 ? $gtm : null,
        ];
    }
}
