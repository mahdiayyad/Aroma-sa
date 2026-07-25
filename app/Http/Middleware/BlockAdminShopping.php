<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Staff/admin accounts run the store — they don't browse or shop it. Applied to
 * the whole web group, so an authenticated admin who lands on any storefront
 * page is returned to the back-office.
 *
 * A short allowlist keeps the essentials reachable: the admin area itself,
 * signing out, switching language, and the CSRF token endpoint.
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

            return redirect()->route('admin.dashboard')->with('error', __('storefront.admin_no_shop'));
        }

        return $next($request);
    }

    /** Routes an admin may still use outside the back-office. */
    private function isAllowed(Request $request): bool
    {
        return $request->is('admin', 'admin/*')
            || $request->is('logout')
            || $request->is('locale/*')
            || $request->is('csrf-token');
    }
}
