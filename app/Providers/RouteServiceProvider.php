<?php

namespace App\Providers;

use App\Support\Email;
use App\Support\Phone;
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
        //
        // Every OTP send is a billed SMS, so this is also the spend guard. The
        // phone is keyed in its canonical form — this middleware runs before
        // request validation, so keying on the raw input would let "05…",
        // "+966 5…" and "+9665…" each get their own allowance for one number.
        RateLimiter::for('otp-send', function (Request $request) {
            $phone = Phone::normalizeSaudi($request->input('phone')) ?? (string) $request->input('phone');
            $tooMany = function () {
                return response()->json(['message' => __('otp.errors.rate_limited')], 429);
            };

            return [
                Limit::perMinutes(10, 3)->by('phone:'.$phone)->response($tooMany),
                Limit::perMinutes(10, 10)->by('ip:'.$request->ip())->response($tooMany),
            ];
        });

        // OTP verify: generous per-phone cap — Tawked enforces the tight
        // per-code brute-force limit (attempts_remaining / too_many_attempts);
        // this route-level limiter mainly stops flooding many send+guess
        // cycles against the same number.
        // Email + password sign-in. Step 1 both guesses a password and, when it's
        // right, emails a code — so it is capped per email (password guessing and
        // how many emails one address can be made to receive) and per IP.
        RateLimiter::for('email-login', function (Request $request) {
            $tooMany = function () {
                return response()->json(['message' => __('otp.errors.rate_limited')], 429);
            };

            return [
                Limit::perMinutes(10, 5)->by('email:'.Email::normalize($request->input('email')))->response($tooMany),
                Limit::perMinutes(10, 20)->by('ip:'.$request->ip())->response($tooMany),
            ];
        });

        // Step 2: guessing the emailed code (the code itself also locks after 5 wrong tries).
        RateLimiter::for('email-verify', function (Request $request) {
            $tooMany = function () {
                return response()->json(['message' => __('otp.errors.rate_limited')], 429);
            };
            $pending = (string) (data_get($request->session()->get('email_login'), 'user_id') ?? optional($request->user())->id ?? 'none');

            return [
                Limit::perMinutes(10, 10)->by('pending:'.$pending.'|'.$request->ip())->response($tooMany),
                Limit::perMinutes(10, 30)->by('ip:'.$request->ip())->response($tooMany),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $phone = Phone::normalizeSaudi($request->input('phone')) ?? (string) $request->input('phone');
            $tooMany = function () {
                return response()->json(['message' => __('otp.errors.rate_limited')], 429);
            };

            return [
                Limit::perMinutes(10, 10)->by('phone:'.$phone)->response($tooMany),
                Limit::perMinutes(10, 20)->by('ip:'.$request->ip())->response($tooMany),
            ];
        });
    }
}
