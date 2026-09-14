<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\OtpCode;
use App\Support\Services\BaseService;
use Illuminate\Support\Facades\Hash;

/**
 * OTP generation/verification. Codes are hashed at rest (Hash::make(), the
 * same slow-hash already used for passwords) and single-use (consumed_at).
 * verify() always targets the single latest row for phone+purpose, so
 * requesting a new code naturally supersedes any earlier one — no explicit
 * invalidation step needed, and an attacker can never usefully hold onto
 * an older code once a newer send has happened.
 */
class OtpService extends BaseService
{
    public const EXPIRY_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;

    public function send(string $phone, string $purpose = OtpCode::PURPOSE_LOGIN): void
    {
        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'ip_address' => request()->ip(),
        ]);

        SendSmsJob::dispatch($phone, __('otp.sms_message', ['code' => $code, 'minutes' => self::EXPIRY_MINUTES]));
    }

    /**
     * @return array{valid:bool,error?:string}
     */
    public function verify(string $phone, string $code, string $purpose = OtpCode::PURPOSE_LOGIN): array
    {
        return $this->transaction(function () use ($phone, $code, $purpose) {
            $otp = OtpCode::where('phone', $phone)->where('purpose', $purpose)
                ->latest('id')->lockForUpdate()->first();

            // Never distinguishes "never requested" from "already used" in
            // the error returned — both look identical to the caller, so a
            // consumed code can't be probed for replay-timing information.
            if (! $otp || $otp->isConsumed()) {
                return ['valid' => false, 'error' => 'not_found'];
            }

            if ($otp->isExpired()) {
                return ['valid' => false, 'error' => 'expired'];
            }

            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                return ['valid' => false, 'error' => 'too_many_attempts'];
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                return ['valid' => false, 'error' => 'invalid_code'];
            }

            $otp->update(['consumed_at' => now()]);

            return ['valid' => true];
        });
    }
}
