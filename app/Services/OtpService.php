<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PhoneVerification;
use App\Services\Otp\TawkedClient;
use App\Support\Services\BaseService;

/**
 * Phone verification on top of the Tawked Verify API. Tawked generates,
 * delivers and checks the one-time code; this service only remembers the
 * verification id it got back (plus the last result we saw), keyed by phone +
 * purpose so callers still deal in "phone + code" and never need the id.
 *
 * verify() always targets the single latest pending row for phone+purpose, so
 * requesting a new code naturally supersedes any earlier one, and a verified
 * (or otherwise closed) row can never be checked again.
 */
class OtpService extends BaseService
{
    private TawkedClient $tawked;

    public function __construct(TawkedClient $tawked)
    {
        $this->tawked = $tawked;
    }

    /**
     * @return array{ok:true,expires_in:int}|array{ok:false,error:string}
     *         error is one of TawkedClient::ERROR_*
     */
    public function send(string $phone, string $purpose = PhoneVerification::PURPOSE_LOGIN): array
    {
        $started = $this->tawked->start($phone, $purpose);

        if (! $started['ok']) {
            return ['ok' => false, 'error' => $started['error']];
        }

        // Supersede only after Tawked accepted the new send, so a failed
        // request never invalidates a code the customer is still holding.
        $this->transaction(function () use ($phone, $purpose, $started) {
            PhoneVerification::where('phone', $phone)
                ->where('purpose', $purpose)
                ->where('status', PhoneVerification::STATUS_PENDING)
                ->update(['status' => PhoneVerification::STATUS_SUPERSEDED]);

            PhoneVerification::create([
                'phone' => $phone,
                'purpose' => $purpose,
                'tawked_id' => $started['id'],
                'status' => PhoneVerification::STATUS_PENDING,
                'expires_at' => $started['expires_at'],
                'ip_address' => request()->ip(),
            ]);
        });

        return ['ok' => true, 'expires_in' => max(0, (int) now()->diffInSeconds($started['expires_at'], false))];
    }

    /**
     * @return array{valid:true}|array{valid:false,error:string}
     *         error: not_found | expired | invalid_code | too_many_attempts | unavailable
     */
    public function verify(string $phone, string $code, string $purpose = PhoneVerification::PURPOSE_LOGIN): array
    {
        $verification = PhoneVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('status', PhoneVerification::STATUS_PENDING)
            ->latest('id')
            ->first();

        // Never distinguishes "never requested" from "already used" — both
        // look identical to the caller, so a consumed code can't be probed
        // for replay-timing information.
        if (! $verification) {
            return ['valid' => false, 'error' => 'not_found'];
        }

        if ($verification->isExpired()) {
            $this->close($verification, PhoneVerification::STATUS_EXPIRED);

            return ['valid' => false, 'error' => 'expired'];
        }

        $result = $this->tawked->check($verification->tawked_id, $code, $phone);

        if (! $result['ok']) {
            // Transport/auth trouble — the row stays pending so the customer
            // can simply try the same code again.
            return ['valid' => false, 'error' => 'unavailable'];
        }

        if ($result['verified']) {
            // Atomic consume: only the request that flips pending→verified
            // wins, so a double-submit can't succeed twice, and no database
            // lock is held across the HTTP call above.
            $consumed = PhoneVerification::whereKey($verification->id)
                ->where('status', PhoneVerification::STATUS_PENDING)
                ->update(['status' => PhoneVerification::STATUS_VERIFIED, 'verified_at' => now()]);

            return $consumed === 1 ? ['valid' => true] : ['valid' => false, 'error' => 'not_found'];
        }

        switch ($result['status']) {
            case 'invalid_code':
                return ['valid' => false, 'error' => 'invalid_code'];

            case 'too_many_attempts':
                $this->close($verification, PhoneVerification::STATUS_FAILED);

                return ['valid' => false, 'error' => 'too_many_attempts'];

            case 'expired':
                $this->close($verification, PhoneVerification::STATUS_EXPIRED);

                return ['valid' => false, 'error' => 'expired'];

            default: // canceled, failed, not_found, or anything Tawked adds later
                $this->close($verification, PhoneVerification::STATUS_FAILED);

                return ['valid' => false, 'error' => 'not_found'];
        }
    }

    private function close(PhoneVerification $verification, string $status): void
    {
        PhoneVerification::whereKey($verification->id)
            ->where('status', PhoneVerification::STATUS_PENDING)
            ->update(['status' => $status]);
    }
}
