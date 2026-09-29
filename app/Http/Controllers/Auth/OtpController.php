<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RespondsToOtpResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpCompleteProfileRequest;
use App\Http\Requests\Auth\OtpSendRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Services\OtpService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The only customer sign-in/sign-up path: mobile number → Tawked OTP → login
 * (existing account) or a one-field profile step (new account). Purely
 * AJAX-driven (no non-JS fallback) since the whole point is the dynamic
 * digit-input UX; every response here is JSON except the profile step.
 *
 * Never reveals at the "send" step whether a phone has an account — that
 * only becomes observable after a *correct* code is verified, so this
 * can't be used to enumerate registered numbers.
 */
class OtpController extends Controller
{
    use RespondsToOtpResults;

    private OtpService $otp;
    private ReferralService $referrals;

    public function __construct(OtpService $otp, ReferralService $referrals)
    {
        $this->otp = $otp;
        $this->referrals = $referrals;
    }

    public function send(OtpSendRequest $request): JsonResponse
    {
        $result = $this->otp->send($request->validated()['phone'], PhoneVerification::PURPOSE_LOGIN);

        if (! $result['ok']) {
            return $this->otpSendFailure($result['error']);
        }

        return response()->json([
            'message' => __('otp.sent'),
            'expires_in' => $result['expires_in'],
        ]);
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->otp->verify($data['phone'], $data['code'], PhoneVerification::PURPOSE_LOGIN);

        if (! $result['valid']) {
            return $this->otpVerifyFailure($result['error']);
        }

        $user = User::where('phone', $data['phone'])->first();

        if ($user) {
            if (! $user->is_active) {
                return response()->json(['message' => __('otp.errors.inactive')], 403);
            }

            $user->forceFill(['phone_verified_at' => now()])->save();
            Auth::login($user, true);

            // Honour where the shopper was headed (e.g. checkout) — the
            // account dashboard is only the fallback.
            $intended = $request->session()->pull('url.intended', route('account.dashboard'));
            $request->session()->regenerate();

            return response()->json(['redirect' => $intended]);
        }

        // No account yet — remember that this exact phone just passed OTP
        // verification (the complete-profile step re-checks this; it can't
        // be reached by simply navigating to the URL without it).
        session(['otp.verified_phone' => $data['phone']]);

        return response()->json(['redirect' => route('otp.complete-profile')]);
    }

    /**
     * @return View|RedirectResponse
     */
    public function showCompleteProfile()
    {
        if (! session('otp.verified_phone')) {
            return redirect()->route('login');
        }

        return view('auth.otp-complete-profile', [
            'phone' => session('otp.verified_phone'),
            'referralPrefill' => session('referral_code_prefill'),
        ]);
    }

    public function completeProfile(OtpCompleteProfileRequest $request): RedirectResponse
    {
        $phone = session('otp.verified_phone');

        if (! $phone) {
            return redirect()->route('login');
        }

        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $phone) {
            // A second tab may have finished signup for this same verified
            // number already — sign in to that account instead of colliding
            // on the unique phone index.
            $existing = User::where('phone', $phone)->first();

            if ($existing) {
                return $existing;
            }

            $user = User::create([
                'name' => $data['name'],
                'phone' => $phone,
                'password' => null, // phone-only account: nothing to remember or reset
                'gender' => 'unspecified',
                'locale' => app()->getLocale(),
            ]);

            $user->forceFill([
                'referral_code' => $this->referrals->generateCode($user),
                'phone_verified_at' => now(),
            ])->save();

            $this->referrals->applyReferral($user, $data['referral_code'] ?? null);

            return $user;
        });

        // `=== false`, not `! is_active`: a just-created user hasn't had the
        // column's DB default loaded onto the model yet (attribute is null).
        if ($user->is_active === false) {
            session()->forget(['otp.verified_phone', 'referral_code_prefill']);

            return redirect()->route('login')->withErrors(['phone' => __('otp.errors.inactive')]);
        }

        session()->forget(['otp.verified_phone', 'referral_code_prefill']);
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with('status', __('auth_ui.flash.registered'));
    }
}
