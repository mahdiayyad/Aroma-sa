<?php

declare(strict_types=1);

namespace App\Support;

/**
 * hreflang alternates for the current request.
 *
 * Storefront pages (home/category/product) are locale-prefixed ("/{locale}/...")
 * so a real URL exists for every supported language. Account/cart/checkout/admin
 * routes are locale-agnostic (language follows the session) — there is no separate
 * URL per language, so no alternates are advertised for them (correctly: Google
 * Search Central warns against hreflang pairs that don't resolve to distinct,
 * equivalent content).
 */
class Seo
{
    /** @return array<string,string> locale => absolute URL */
    public static function alternateUrls(): array
    {
        $route = request()->route();

        if (! $route || ! array_key_exists('locale', $route->parameters())) {
            return [];
        }

        $params = $route->parameters();
        $urls = [];

        foreach (array_keys(config('aroma.locales', [])) as $locale) {
            $params['locale'] = $locale;
            $urls[$locale] = route($route->getName(), $params);
        }

        return $urls;
    }
}
