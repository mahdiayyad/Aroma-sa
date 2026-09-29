<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RespondsToOtpResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailCodeRequest;
use App\Http\Requests\Auth\EmailLoginRequest;
use App\Models\EmailCode;
use App\Models\User;
use App\Services\EmailCodeService;
use App\Support\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Email + password sign-in, in two steps so a stolen password alone never logs
 * anyone in: (1) email + password are checked and a one-time code is emailed —
 * the account is only remembered in the SESSION, nobody is signed in yet;
 * (2) the emailed code is checked and only then does the session get logged in.
 *
 * Every credential failure — unknown email, wrong password, an account with no
 * password (phone-only) — returns the identical message, and a dummy hash check
 * keeps the timing the same, so this can't be used to find out which emails
 * have accounts.
 */
class EmailLoginController extends Controller
{
    use RespondsToOtpResults;

    private const PENDING_TTL_MINUTES = 15;

    /** A valid bcrypt hash of nothing useful — checked when there is no real one. */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private EmailCodeService $codes;

    public function __construct(EmailCodeService $codes)
    {
        $this->codes = $codes;
    }

    public function send(EmailLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $email = $data['email'];

        $user = User::where('email', $email)->first();
        $hash = $user && $user->password ? $user->password : self::DUMMY_HASH;
        $passwordOk = Hash::check($data['password'], $hash) && $user && $user->password;

        if (! $passwordOk) {
            session()->forget('email_login');

            return response()->json(['message' => __('email_auth.errors.invalid_credentials')], 422);
        }

        if ($user->is_active === false) {
            return response()->json(['message' => __('otp.errors.inactive')], 403);
        }

        if (! $this->codes->send($email, EmailCode::PURPOSE_LOGIN)) {
            return response()->json(['message' => __('email_auth.errors.send_failed')], 503);
        }

        session(['email_login' => [
            'user_id' => $user->id,
            'email' => $email,
            'expires_at' => now()->addMinutes(self::PENDING_TTL_MINUTES)->timestamp,
        ]]);

        return response()->json([
            'message' => __('email_auth.sent'),
            'display' => Email::mask($email),
            'expires_in' => EmailCodeService::EXPIRY_MINUTES * 60,
        ]);
    }

    public function verify(EmailCodeRequest $request): JsonResponse
    {
        $pending = session('email_login');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget('email_login');

            return response()->json(['message' => __('email_auth.errors.session_expired')], 422);
        }

        $result = $this->codes->verify($pending['email'], $request->validated()['code'], EmailCode::PURPOSE_LOGIN);

        if (! $result['valid']) {
            // Out of guesses for this code: the password step must be repeated.
            if ($result['error'] === 'too_many_attempts') {
                session()->forget('email_login');
            }

            return $this->otpVerifyFailure($result['error']);
        }

        $user = User::find($pending['user_id']);

        // The account could have been disabled, or its email changed by an admin,
        // between the two steps.
        if (! $user || Email::normalize($user->email) !== $pending['email']) {
            session()->forget('email_login');

            return response()->json(['message' => __('email_auth.errors.session_expired')], 422);
        }

        if ($user->is_active === false) {
            session()->forget('email_login');

            return response()->json(['message' => __('otp.errors.inactive')], 403);
        }

        session()->forget('email_login');
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        Auth::login($user, true);

        $intended = $request->session()->pull('url.intended', route('account.dashboard'));
        $request->session()->regenerate();

        return response()->json(['redirect' => $intended]);
    }
}
