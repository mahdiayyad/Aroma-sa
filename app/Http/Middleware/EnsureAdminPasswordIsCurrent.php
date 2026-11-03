<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gates the rest of the back-office behind a forced password change: a
 * freshly created staff/admin account (aroma:make-admin) is only given the
 * temporary password relayed to them by whoever created it, so it can't be
 * trusted as theirs alone until they've set their own. Runs after 'admin',
 * inside the same ['auth', 'admin'] group, wrapping every route except the
 * change-password screen itself (otherwise the redirect would loop).
 */
class EnsureAdminPasswordIsCurrent
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            return redirect()->route('admin.password.change');
        }

        return $next($request);
    }
}
