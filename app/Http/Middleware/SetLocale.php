<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Resolves the active locale from the {locale} route prefix and applies it to
 * the application. Also persists the choice so non-prefixed entry points
 * (e.g. a shared link without a prefix) can honour the visitor's last choice.
 *
 * The bilingual store launches with Arabic (RTL) and English (LTR); the list
 * of supported locales lives in config/aroma.php.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = array_keys(config('aroma.locales', ['ar' => [], 'en' => []]));
        $default   = config('aroma.default_locale', config('app.fallback_locale', 'en'));

        // 1) Explicit route prefix wins.
        $locale = $request->route('locale');

        // 2) Fall back to the persisted session choice, then the default.
        if (! in_array($locale, $supported, true)) {
            $locale = $request->session()->get('locale', $default);
        }

        if (! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        // Expose direction to views without a second config lookup.
        view()->share('locale', $locale);
        view()->share('direction', config("aroma.locales.$locale.dir", 'ltr'));

        return $next($request);
    }
}
