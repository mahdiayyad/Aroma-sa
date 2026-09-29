<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EmailCodeMail;
use App\Models\EmailCode;
use App\Support\Email;
use App\Support\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One-time codes sent by email (email + password sign-in, and confirming an
 * email address added in Profile). Tawked can't do email, so — unlike the phone
 * flow — the code is ours: hashed at rest (Hash::make(), the slow hash used for
 * passwords), single-use (consumed_at) and capped at MAX_ATTEMPTS wrong guesses.
 * verify() always targets the single latest row for email+purpose, so asking
 * for a new code supersedes any earlier one.
 */
class EmailCodeService extends BaseService
{
    public const EXPIRY_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    /**
     * @return bool false when the email could not be sent (nothing is kept)
     */
    public function send(string $email, string $purpose): bool
    {
        $email = Email::normalize($email);
        $code = (string) random_int(100000, 999999);

        $row = EmailCode::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'ip_address' => request()->ip(),
        ]);

        try {
            // Synchronous on purpose: the customer is waiting for this code and
            // no queue worker runs here (same reason the contact form sends inline).
            Mail::to($email)->send(new EmailCodeMail($code, $purpose, self::EXPIRY_MINUTES));
        } catch (Throwable $e) {
            Log::error('Email code could not be sent', ['email' => Email::mask($email), 'error' => $e->getMessage()]);
            $row->delete();

            return false;
        }

        return true;
    }

    /**
     * @return array{valid:true}|array{valid:false,error:string}
     *         error: not_found | expired | invalid_code | too_many_attempts
     */
    public function verify(string $email, string $code, string $purpose): array
    {
        $email = Email::normalize($email);

        return $this->transaction(function () use ($email, $code, $purpose) {
            $row = EmailCode::where('email', $email)->where('purpose', $purpose)
                ->latest('id')->lockForUpdate()->first();

            // "Never requested" and "already used" read identically, so a
            // consumed code can't be probed for replay-timing information.
            if (! $row || $row->isConsumed()) {
                return ['valid' => false, 'error' => 'not_found'];
            }

            if ($row->isExpired()) {
                return ['valid' => false, 'error' => 'expired'];
            }

            if ($row->attempts >= self::MAX_ATTEMPTS) {
                return ['valid' => false, 'error' => 'too_many_attempts'];
            }

            if (! Hash::check($code, $row->code_hash)) {
                $row->increment('attempts');

                return ['valid' => false, 'error' => 'invalid_code'];
            }

            $row->update(['consumed_at' => now()]);

            return ['valid' => true];
        });
    }
}
