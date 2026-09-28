<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Concerns\RespondsToOtpResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailCodeRequest;
use App\Models\EmailCode;
use App\Models\User;
use App\Services\EmailCodeService;
use App\Support\Email;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Profile → "Email & password sign-in". A phone-signup account has no password;
 * this adds an email + password so it can also use the email sign-in option.
 * Nothing is written until the emailed code proves the address is the caller's:
 * the (hashed) password and address wait in the SESSION meanwhile, so a typo'd
 * or someone-else's email never gets attached to the account.
 */
class EmailPasswordController extends Controller
{
    use RespondsToOtpResults;

    private const PENDING_TTL_MINUTES = 30;

    private EmailCodeService $codes;

    public function __construct(EmailCodeService $codes)
    {
        $this->codes = $codes;
    }

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->password) {
            return response()->json(['message' => __('email_auth.profile.already_set')], 422);
        }

        $request->merge(['email' => Email::normalize($request->input('email'))]);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! $this->codes->send($data['email'], EmailCode::PURPOSE_ADD_CREDENTIALS)) {
            return response()->json(['message' => __('email_auth.errors.send_failed')], 503);
        }

        session(['add_credentials' => [
            'user_id' => $user->id,
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
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
        $user = $request->user();
        $pending = session('add_credentials');

        if (! is_array($pending) || $pending['user_id'] !== $user->id || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget('add_credentials');

            return response()->json(['message' => __('email_auth.profile.session_expired')], 422);
        }

        $result = $this->codes->verify($pending['email'], $request->validated()['code'], EmailCode::PURPOSE_ADD_CREDENTIALS);

        if (! $result['valid']) {
            if ($result['error'] === 'too_many_attempts') {
                session()->forget('add_credentials');
            }

            return $this->otpVerifyFailure($result['error']);
        }

        $taken = User::where('email', $pending['email'])->where('id', '!=', $user->id)->exists();

        if ($taken || $user->fresh()->password) {
            session()->forget('add_credentials');

            return response()->json(['message' => __('email_auth.profile.session_expired')], 422);
        }

        try {
            $user->forceFill([
                'email' => $pending['email'],
                'password' => $pending['password_hash'],
                'email_verified_at' => now(),
            ])->save();
        } catch (QueryException $e) {
            // Lost a race for the same address.
            session()->forget('add_credentials');

            return response()->json(['message' => __('email_auth.profile.session_expired')], 422);
        }

        session()->forget('add_credentials');
        session()->flash('status', __('email_auth.profile.added'));

        return response()->json(['redirect' => route('account.profile.edit')]);
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->password) {
            return back()->with('error', __('email_auth.profile.session_expired'));
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('email_auth.profile.wrong_current')]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('status', __('email_auth.profile.password_changed'));
    }
}
