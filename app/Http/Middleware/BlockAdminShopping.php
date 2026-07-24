<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Staff/admin accounts run the store — they must not shop or check out with it.
 * Applied to the cart + checkout flows; sends admins back to the back-office.
 */
class BlockAdminShopping
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('storefront.admin_no_shop')], 403);
            }

            return redirect()->route('admin.dashboard')->with('error', __('storefront.admin_no_shop'));
        }

        return $next($request);
    }
}
