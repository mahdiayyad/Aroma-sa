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
 * OAuth sign-in via Socialite. Google is supported out of the box; Apple
 * requires the socialiteproviders/apple community driver (documented). A
 * provider is only offered when its credentials are configured, so the flow
 * degrades gracefully in environments without keys.
 */
class SocialAuthController extends Controller
{
    private const SUPPORTED = ['google', 'apple'];

    public function redirect(string $provider): RedirectResponse
    {
        if (! $this->isConfigured($provider)) {
            return redirect()->route('login')->withErrors(['login' => __('auth_ui.social.unavailable')]);
        }

        try {
            return Socialite::driver($provider)->redirect();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors(['login' => __('auth_ui.social.unavailable')]);
        }
    }

    public function callback(string $provider): RedirectResponse
    {
        if (! $this->isConfigured($provider)) {
            return redirect()->route('login')->withErrors(['login' => __('auth_ui.social.unavailable')]);
        }

        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors(['login' => __('auth_ui.social.failed')]);
        }

        $user = $this->findOrCreateUser($provider, $oauthUser);

        Auth::login($user, true);

        return redirect()->route('account.dashboard')->with('status', __('auth_ui.flash.logged_in'));
    }

    private function isConfigured(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED, true)
            && ! empty(config("services.$provider.client_id"));
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
        return User::create([
            'name'              => $oauthUser->getName() ?: Str::before((string) $oauthUser->getEmail(), '@') ?: 'Aroma Customer',
            'email'             => $oauthUser->getEmail(),
            'provider'          => $provider,
            'provider_id'       => $oauthUser->getId(),
            'avatar'            => $oauthUser->getAvatar(),
            'locale'            => app()->getLocale(),
            'email_verified_at' => now(),
        ]);
    }
}
