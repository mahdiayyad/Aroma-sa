<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Which ad pixels the current page should load. Production only, so local and
 * staging traffic never reaches the ad accounts, and never for signed-in staff
 * or admins (they can browse the store through "View store").
 */
class MarketingPixels
{
    /** @return array{meta:?string,tiktok:?string} */
    public static function ids(): array
    {
        $user = auth()->user();

        if (! app()->environment('production') || ($user && $user->isAdmin())) {
            return ['meta' => null, 'tiktok' => null];
        }

        return [
            'meta' => config('aroma.marketing.meta_pixel_id') ?: null,
            'tiktok' => config('aroma.marketing.tiktok_pixel_id') ?: null,
        ];
    }
}
