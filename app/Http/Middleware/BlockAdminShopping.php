<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Staff/admin accounts run the store — they don't shop it, but they can look at
 * it. Applied to the whole web group, so an authenticated admin who lands on
 * any storefront page is normally returned to the back-office.
 *
 * A short allowlist keeps the essentials reachable: the admin area itself,
 * signing out, switching language, and the CSRF token endpoint.
 *
 * "View store" (admin sidebar/topbar) opens the storefront through
 * `admin.preview-store`, which sets a `admin_store_preview` session flag. While
 * it's set, read-only GET/HEAD browsing of the general storefront is allowed
 * too — but not any authenticated-customer or transactional path (cart,
 * checkout, account, wishlist, orders, auth), and not any non-GET request
 * (adding to cart, submitting a review, the contact form, …), so an admin can
 * look around without ever being able to actually place an order. A blocked
 * action while previewing sends them back to the page they were on instead of
 * ending the preview; `admin.exit-preview` clears the flag explicitly.
 */
class BlockAdminShopping
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isAdmin() && ! $this->isAllowed($request)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('storefront.admin_no_shop')], 403);
            }

            if ($this->isPreviewing($request)) {
                return redirect()->back()->with('error', __('storefront.admin_no_shop'));
            }

            return redirect()->route('admin.dashboard')->with('error', __('storefront.admin_no_shop'));
        }

        return $next($request);
    }

    /** Routes an admin may always use, preview mode or not. */
    private function isAllowed(Request $request): bool
    {
        return $request->is('admin', 'admin/*')
            || $request->is('logout')
            || $request->is('locale/*')
            || $request->is('csrf-token')
            || $this->isPreviewBrowsing($request);
    }

    private function isPreviewing(Request $request): bool
    {
        return (bool) $request->session()->get('admin_store_preview');
    }

    /** A safe, read-only page reachable only while "View store" preview mode is on. */
    private function isPreviewBrowsing(Request $request): bool
    {
        if (! $this->isPreviewing($request)) {
            return false;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        // Every authenticated-customer / transactional area stays off-limits even
        // while previewing — this is the "look, don't shop" boundary.
        return ! $request->is(
            'account', 'account/*',
            'cart', 'cart/*',
            'checkout', 'checkout/*',
            'wishlist', 'wishlist/*',
            'orders', 'orders/*',
            'order/*',
            'login', 'register', 'password/*', 'auth/*', 'otp/*'
        );
    }
}
