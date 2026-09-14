<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpCompleteProfileRequest;
use App\Http\Requests\Auth\OtpSendRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Passwordless phone auth: Phone → send OTP → verify OTP → login (existing
 * account) or a short profile-completion step (new account, password left
 * null — the same pattern already used for social-login accounts). Purely
 * AJAX-driven (no non-JS fallback) since the whole point is the dynamic
 * digit-input UX; every response here is JSON.
 *
 * Never reveals at the "send" step whether a phone has an account — that
 * only becomes observable after a *correct* code is verified, so this
 * can't be used to enumerate registered numbers.
 */
class OtpController extends Controller
{
    private OtpService $otp;
    private ReferralService $referrals;

    public function __construct(OtpService $otp, ReferralService $referrals)
    {
        $this->otp = $otp;
        $this->referrals = $referrals;
    }

    public function send(OtpSendRequest $request): JsonResponse
    {
        $this->otp->send($request->validated()['phone'], OtpCode::PURPOSE_LOGIN);

        return response()->json([
            'message' => __('otp.sent'),
            'expires_in' => OtpService::EXPIRY_MINUTES * 60,
        ]);
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->otp->verify($data['phone'], $data['code'], OtpCode::PURPOSE_LOGIN);

        if (! $result['valid']) {
            return response()->json(['message' => __('otp.errors.'.$result['error'])], 422);
        }

        $user = User::where('phone', $data['phone'])->first();

        if ($user) {
            $user->forceFill(['phone_verified_at' => now()])->save();
            Auth::login($user, true);
            $request->session()->regenerate();

            return response()->json(['redirect' => route('account.dashboard')]);
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

        return view('auth.otp-complete-profile', ['phone' => session('otp.verified_phone')]);
    }

    public function completeProfile(OtpCompleteProfileRequest $request): RedirectResponse
    {
        $phone = session('otp.verified_phone');

        if (! $phone) {
            return redirect()->route('login');
        }

        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $phone) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $phone,
                'password' => null, // OTP-only account — same nullable-password pattern as social logins
                'gender' => $data['gender'] ?? 'unspecified',
                'locale' => app()->getLocale(),
            ]);

            $user->forceFill([
                'referral_code' => $this->referrals->generateCode($user),
                'phone_verified_at' => now(),
            ])->save();

            $this->referrals->applyReferral($user, $data['referral_code'] ?? null);

            return $user;
        });

        session()->forget('otp.verified_phone');
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with('status', __('auth_ui.flash.registered'));
    }
}
