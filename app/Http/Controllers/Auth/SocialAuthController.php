<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * OAuth sign-in via Socialite. Currently Google only — the provider is only
 * offered when its credentials are configured, so the flow degrades
 * gracefully in environments without keys.
 */
class SocialAuthController extends Controller
{
    private const SUPPORTED = ['google', 'apple'];

    /**
     * Which SUPPORTED providers actually have credentials set right now —
     * shared into the auth views (see AromaServiceProvider's auth.* view
     * composer) so a button is never shown for a provider that would just
     * fail on click. Apple has no real credentials configured anywhere yet,
     * so its button stays hidden until real ones are provisioned.
     *
     * @return string[]
     */
    public static function configuredProviders(): array
    {
        return array_values(array_filter(self::SUPPORTED, function (string $provider) {
            return ! empty(config("services.$provider.client_id"));
        }));
    }

    public function redirect(string $provider): RedirectResponse
    {
        // Remember which auth page (login vs register) sent the shopper here,
        // so any failure below — including "not configured" — can return them
        // there instead of always landing on the login page. Set before the
        // config check so it's captured regardless of which branch bails.
        session(['social_origin' => url()->previous()]);

        if (! $this->isConfigured($provider)) {
            return $this->failureRedirect(__('auth_ui.social.unavailable'));
        }

        try {
            return Socialite::driver($provider)->redirect();
        } catch (Throwable $e) {
            return $this->failureRedirect(__('auth_ui.social.unavailable'));
        }
    }

    public function callback(string $provider): RedirectResponse
    {
        if (! $this->isConfigured($provider)) {
            return $this->failureRedirect(__('auth_ui.social.unavailable'));
        }

        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            return $this->failureRedirect(__('auth_ui.social.failed'));
        }

        $user = $this->findOrCreateUser($provider, $oauthUser);

        Auth::login($user, true);
        session()->forget('social_origin');

        return redirect()->route('account.dashboard')->with('status', __('auth_ui.flash.logged_in'));
    }

    private function isConfigured(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED, true)
            && in_array($provider, self::configuredProviders(), true);
    }

    /**
     * Redirect a failed attempt back to wherever it started (login or
     * register) rather than always defaulting to login. Uses a "social" error
     * key — not "login" — so the message doesn't get misattributed to the
     * login form's email/phone field (that field is literally named "login").
     */
    private function failureRedirect(string $message): RedirectResponse
    {
        $origin = session()->pull('social_origin');
        $knownAuthPage = $origin && Str::startsWith($origin, [route('login'), route('register')]);

        return redirect($knownAuthPage ? $origin : route('login'))->withErrors(['social' => $message]);
    }

    /** @param \Laravel\Socialite\Contracts\User $oauthUser */
    private function findOrCreateUser(string $provider, $oauthUser): User
    {
        // 1) Existing social identity.
        $user = User::where('provider', $provider)->where('provider_id', $oauthUser->getId())->first();
        if ($user) {
            return $user;
        }

        // 2) Link to an existing account with the same email.
        if ($oauthUser->getEmail()) {
            $user = User::where('email', $oauthUser->getEmail())->first();
            if ($user) {
                $user->forceFill(['provider' => $provider, 'provider_id' => $oauthUser->getId()])->save();

                return $user;
            }
        }

        // 3) Brand new account.
        $user = User::create([
            'name'        => $oauthUser->getName() ?: Str::before((string) $oauthUser->getEmail(), '@') ?: 'Aroma Customer',
            'email'       => $oauthUser->getEmail(),
            'provider'    => $provider,
            'provider_id' => $oauthUser->getId(),
            'avatar'      => $oauthUser->getAvatar(),
            'locale'      => app()->getLocale(),
        ]);

        // email_verified_at is deliberately not mass-assignable (it must
        // never be settable from ordinary request input) — the provider has
        // already verified this address, so it's safe to stamp explicitly.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
