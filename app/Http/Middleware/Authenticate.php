<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Back-office visitors get the admin sign-in page, not the storefront one.
        if ($request->is('admin') || $request->is('admin/*')) {
            return route('admin.login');
        }

        return route('login');
    }
}
