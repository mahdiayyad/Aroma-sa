<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Concerns\RespondsToOtpResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpSendRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

/**
 * Changing the mobile number = changing the sign-in credential, so it is never
 * a plain form field: a code is sent to the NEW number and the change only
 * happens once that code checks out (the same Tawked flow as signing in).
 * Without this, a hijacked session could swap the number and lock the real
 * owner out for good — there is no password to fall back on.
 */
class PhoneChangeController extends Controller
{
    use RespondsToOtpResults;

    private OtpService $otp;

    public function __construct(OtpService $otp)
    {
        $this->otp = $otp;
    }

    public function send(OtpSendRequest $request): JsonResponse
    {
        $phone = $request->validated()['phone'];

        if ($phone === $request->user()->phone) {
            $message = __('otp.change_phone.same');

            return response()->json(['message' => $message, 'errors' => ['phone' => [$message]]], 422);
        }

        // Deliberately does NOT say whether another account already owns this
        // number — that is only revealed after the code proves the caller has
        // the number, so this can't be used to enumerate registered phones.
        $result = $this->otp->send($phone, PhoneVerification::PURPOSE_CHANGE_PHONE);

        if (! $result['ok']) {
            return $this->otpSendFailure($result['error']);
        }

        return response()->json(['message' => __('otp.sent'), 'expires_in' => $result['expires_in']]);
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->otp->verify($data['phone'], $data['code'], PhoneVerification::PURPOSE_CHANGE_PHONE);

        if (! $result['valid']) {
            return $this->otpVerifyFailure($result['error']);
        }

        $user = $request->user();

        $taken = response()->json(['message' => __('otp.change_phone.taken')], 422);

        if (User::where('phone', $data['phone'])->where('id', '!=', $user->id)->exists()) {
            return $taken;
        }

        try {
            $user->forceFill(['phone' => $data['phone'], 'phone_verified_at' => now()])->save();
        } catch (QueryException $e) {
            // Lost a race against another account claiming the same number.
            return $taken;
        }

        session()->flash('status', __('otp.change_phone.done'));

        return response()->json(['redirect' => route('account.profile.edit')]);
    }
}
