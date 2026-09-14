<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/account';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // OTP send: capped per phone number (the actual abuse target — an
        // attacker can rotate IPs but not the victim's phone) AND per IP
        // (stops one client from spamming OTPs to many different numbers).
        // Both limits apply simultaneously — Laravel accepts an array here.
        RateLimiter::for('otp-send', function (Request $request) {
            return [
                Limit::perMinutes(10, 3)->by('phone:'.$request->input('phone')),
                Limit::perMinutes(10, 10)->by('ip:'.$request->ip()),
            ];
        });

        // OTP verify: generous per-phone cap — the tighter brute-force gate
        // is OtpCode.attempts (max 5 wrong guesses per code, see OtpService);
        // this route-level limiter mainly stops flooding many send+guess
        // cycles against the same number.
        RateLimiter::for('otp-verify', function (Request $request) {
            return [
                Limit::perMinutes(10, 10)->by('phone:'.$request->input('phone')),
                Limit::perMinutes(10, 20)->by('ip:'.$request->ip()),
            ];
        });
    }
}
