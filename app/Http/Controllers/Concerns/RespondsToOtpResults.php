<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Services\Otp\TawkedClient;
use Illuminate\Http\JsonResponse;

/**
 * Shared JSON responses for the phone-OTP endpoints (sign-in and the profile
 * "change number" flow), in the shapes public/js/otp-auth.js already renders.
 */
trait RespondsToOtpResults
{
    /** @param string $error one of TawkedClient::ERROR_* */
    private function otpSendFailure(string $error): JsonResponse
    {
        switch ($error) {
            case TawkedClient::ERROR_INVALID_PHONE:
                $message = __('otp.errors.invalid_destination');

                return response()->json(['message' => $message, 'errors' => ['phone' => [$message]]], 422);

            case TawkedClient::ERROR_RATE_LIMITED:
                return response()->json(['message' => __('otp.errors.rate_limited')], 429);

            default:
                return response()->json(['message' => __('otp.errors.unavailable')], 503);
        }
    }

    /** @param string $error one of OtpService::verify()'s error codes */
    private function otpVerifyFailure(string $error): JsonResponse
    {
        return response()->json(['message' => __('otp.errors.'.$error)], $error === 'unavailable' ? 503 : 422);
    }
}
