<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RespondsToOtpResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailCodeRequest;
use App\Http\Requests\Auth\EmailRegisterRequest;
use App\Models\EmailCode;
use App\Models\User;
use App\Services\EmailCodeService;
use App\Services\ReferralService;
use App\Support\Email;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Sign-up's second door: create an account with email + password, confirmed by
 * a code emailed to that address (same engine as EmailLoginController /
 * Account\EmailPasswordController). Nothing is written to `users` until the
 * code checks out — the (hashed) password, name and referral code wait in the
 * SESSION meanwhile, so a typo'd or someone-else's address never creates
 * anything.
 */
class EmailRegisterController extends Controller
{
    use RespondsToOtpResults;

    private const PENDING_TTL_MINUTES = 30;

    private EmailCodeService $codes;
    private ReferralService $referrals;

    public function __construct(EmailCodeService $codes, ReferralService $referrals)
    {
        $this->codes = $codes;
        $this->referrals = $referrals;
    }

    public function send(EmailRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $this->codes->send($data['email'], EmailCode::PURPOSE_REGISTER)) {
            return response()->json(['message' => __('email_auth.errors.send_failed')], 503);
        }

        session(['email_register' => [
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'referral_code' => $data['referral_code'] ?? null,
            'expires_at' => now()->addMinutes(self::PENDING_TTL_MINUTES)->timestamp,
        ]]);

        return response()->json([
            'message' => __('email_auth.sent'),
            'display' => Email::mask($data['email']),
            'expires_in' => EmailCodeService::EXPIRY_MINUTES * 60,
        ]);
    }

    public function verify(EmailCodeRequest $request): JsonResponse
    {
        $pending = session('email_register');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget('email_register');

            return response()->json(['message' => __('email_auth.register.session_expired')], 422);
        }

        $result = $this->codes->verify($pending['email'], $request->validated()['code'], EmailCode::PURPOSE_REGISTER);

        if (! $result['valid']) {
            if ($result['error'] === 'too_many_attempts') {
                session()->forget('email_register');
            }

            return $this->otpVerifyFailure($result['error']);
        }

        // The address was free when step 1 ran but may not be any more (e.g.
        // claimed via the phone-account "add email" flow in the meantime) —
        // this session's password was never checked against that account, so
        // it must never be able to sign into it.
        if (User::where('email', $pending['email'])->exists()) {
            session()->forget('email_register');

            return response()->json(['message' => __('email_auth.register.session_expired')], 422);
        }

        try {
            $user = DB::transaction(function () use ($pending) {
                $user = User::create([
                    'name' => $pending['name'],
                    'email' => $pending['email'],
                    'password' => $pending['password_hash'],
                    'gender' => 'unspecified',
                    'locale' => app()->getLocale(),
                ]);

                $user->forceFill([
                    'referral_code' => $this->referrals->generateCode($user),
                    'email_verified_at' => now(),
                ])->save();

                $this->referrals->applyReferral($user, $pending['referral_code'] ?? null);

                return $user;
            });
        } catch (QueryException $e) {
            // Lost a race for the same address.
            session()->forget('email_register');

            return response()->json(['message' => __('email_auth.register.session_expired')], 422);
        }

        session()->forget(['email_register', 'referral_code_prefill']);

        // `=== false`, not `! is_active`: a just-created user hasn't had the
        // column's DB default loaded onto the model yet (attribute is null).
        if ($user->is_active === false) {
            return response()->json(['message' => __('otp.errors.inactive')], 403);
        }

        Auth::login($user, true);
        $intended = $request->session()->pull('url.intended', route('account.dashboard'));
        $request->session()->regenerate();

        return response()->json(['redirect' => $intended]);
    }
}
