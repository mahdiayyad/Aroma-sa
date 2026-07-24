<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gates the /admin back-office. Assumes the `auth` middleware ran first, so an
 * unauthenticated visitor is already redirected to login; here we only reject
 * authenticated non-staff users.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        abort_unless($user && $user->isAdmin(), 403);

        return $next($request);
    }
}
