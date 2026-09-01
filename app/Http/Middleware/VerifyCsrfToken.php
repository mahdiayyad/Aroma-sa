<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Moyasar's server-to-server payment webhook — it can never carry a
        // Laravel session/CSRF token (it's not a browser request), so
        // without this exemption every real webhook delivery was silently
        // rejected with a 419 before MoyasarWebhookController ever ran its
        // own (correct) HMAC signature verification. That signature check
        // is the actual authenticity guard for this route, not CSRF.
        'webhooks/moyasar',
    ];
}
