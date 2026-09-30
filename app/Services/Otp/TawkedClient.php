<?php

declare(strict_types=1);

namespace App\Services\Otp;

use App\Support\Phone;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin client for the Tawked Verify API (https://tawked.com/openapi.json).
 * Tawked generates, delivers and checks the one-time code; this app only ever
 * holds the verification id it hands back.
 *
 * Fails CLOSED: with no API key configured every call reports "unavailable" —
 * there is deliberately no stub that "succeeds", because a fake verify would
 * let anyone log in as anyone. The single exception is the local-only dev
 * bypass (see devBypassActive()), which is impossible to enable outside
 * APP_ENV=local.
 */
class TawkedClient
{
    public const ERROR_INVALID_PHONE = 'invalid_phone';
    public const ERROR_RATE_LIMITED = 'rate_limited';
    public const ERROR_UNAVAILABLE = 'unavailable';

    /** Tawked 422 codes that mean "this number can't receive a code". */
    private const INVALID_DESTINATION_CODES = ['invalid_destination', 'sandbox_unverified_destination'];

    /** Tawked 429 codes that are the caller's own throttle (vs. our account limits). */
    private const THROTTLE_CODES = ['too_many_requests', 'rate_limited', 'ip_rate_limited'];

    public function isConfigured(): bool
    {
        return (string) config('services.tawked.api_key') !== '';
    }

    /**
     * Local-development shortcut: with TAWKED_DEV_CODE set, start() skips the
     * HTTP call and check() accepts that fixed code. Hard-gated on
     * APP_ENV=local — staging, production and the test suite ignore it.
     */
    public function devBypassActive(): bool
    {
        return app()->environment('local') && (string) config('services.tawked.dev_code') !== '';
    }

    /**
     * @return array{ok:true,id:string,expires_at:Carbon}|array{ok:false,error:string}
     */
    public function start(string $phone, string $purpose): array
    {
        if ($this->devBypassActive()) {
            return ['ok' => true, 'id' => 'dev-'.Str::uuid(), 'expires_at' => now()->addMinutes(5)];
        }

        $response = $this->post('/v1/verify/start', [
            'to' => $phone,
            'channel' => 'sms',
            'lang' => app()->getLocale() === 'en' ? 'en' : 'ar',
            'reference' => $purpose,
            'client_ip' => request()->ip(),
        ], $phone);

        if ($response === null) {
            return ['ok' => false, 'error' => self::ERROR_UNAVAILABLE];
        }

        $error = (string) $response->json('error', '');

        if ($response->status() === 201 && is_string($response->json('id'))) {
            return [
                'ok' => true,
                'id' => (string) $response->json('id'),
                'expires_at' => $this->parseExpiry($response->json('expires_at')),
            ];
        }

        $this->logFailure('start', $response, $phone);

        if ($response->status() === 422 && in_array($error, self::INVALID_DESTINATION_CODES, true)) {
            return ['ok' => false, 'error' => self::ERROR_INVALID_PHONE];
        }

        if ($response->status() === 429 && in_array($error, self::THROTTLE_CODES, true)) {
            return ['ok' => false, 'error' => self::ERROR_RATE_LIMITED];
        }

        // Everything else (bad key, no credit, spend/sandbox caps, 5xx, …) is
        // our problem, not something the customer can fix by retyping.
        return ['ok' => false, 'error' => self::ERROR_UNAVAILABLE];
    }

    /**
     * Wrong / expired / exhausted codes are NOT errors here — Tawked answers
     * 200 with verified:false and a status. Only transport/auth problems
     * return ok:false.
     *
     * @return array{ok:true,verified:bool,status:string}|array{ok:false,error:string}
     */
    public function check(string $verificationId, string $code, string $phone = ''): array
    {
        if ($this->devBypassActive() && Str::startsWith($verificationId, 'dev-')) {
            $match = hash_equals((string) config('services.tawked.dev_code'), $code);

            return ['ok' => true, 'verified' => $match, 'status' => $match ? 'verified' : 'invalid_code'];
        }

        $response = $this->post('/v1/verify/check', ['id' => $verificationId, 'code' => $code], $phone);

        if ($response === null) {
            return ['ok' => false, 'error' => self::ERROR_UNAVAILABLE];
        }

        if ($response->status() === 200 && is_bool($response->json('verified'))) {
            return [
                'ok' => true,
                'verified' => (bool) $response->json('verified'),
                'status' => (string) $response->json('status', ''),
            ];
        }

        // Unknown/forgotten verification id: not a service outage — the code
        // simply can't be checked, so let the caller treat it as "not found".
        if ($response->status() === 404) {
            return ['ok' => true, 'verified' => false, 'status' => 'not_found'];
        }

        $this->logFailure('check', $response, $phone);

        return ['ok' => false, 'error' => self::ERROR_UNAVAILABLE];
    }

    /** Sends the request; returns null when unconfigured or the transport fails. */
    private function post(string $path, array $payload, string $phone): ?Response
    {
        if (! $this->isConfigured()) {
            Log::error('Tawked API key is not configured — phone verification is unavailable.');

            return null;
        }

        try {
            return Http::withToken((string) config('services.tawked.api_key'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.tawked.timeout', 10))
                ->post(rtrim((string) config('services.tawked.base_url'), '/').$path, $payload);
        } catch (Throwable $e) {
            Log::error('Tawked request failed (transport)', [
                'endpoint' => $path,
                'phone' => Phone::mask($phone),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function logFailure(string $endpoint, Response $response, string $phone): void
    {
        Log::warning('Tawked request rejected', [
            'endpoint' => $endpoint,
            'http_status' => $response->status(),
            'error' => $response->json('error'),
            'phone' => Phone::mask($phone),
        ]);
    }

    /**
     * Customers reported a code reading as "expired" within seconds of being
     * sent. That can only ever come from OUR OWN local pre-check in
     * OtpService::verify() (PhoneVerification::isExpired()) — Tawked's own
     * /verify/check is the actual authority on whether a code is still
     * good, and it doesn't consult anything stored here. So rather than
     * only guarding against obviously-broken values (unparseable, already
     * past), this now floors whatever Tawked returns to a 5-minute minimum
     * outright — any of a bad clock on this server, a timezone misparse of
     * a value Tawked sent without one, or Tawked simply issuing a shorter
     * window, would otherwise make our local check block a customer before
     * Tawked itself would. Flooring closes all of those at once: our
     * pre-check can now only ever be MORE lenient than Tawked's real
     * deadline, never less, and Tawked's own check still has the final say
     * on every code regardless of what's stored here.
     */
    private function parseExpiry($value): Carbon
    {
        $floor = now()->addMinutes(5);

        try {
            $parsed = $value ? Carbon::parse($value) : null;
        } catch (Throwable $e) {
            Log::warning('Tawked returned an unparseable expires_at — using the default window', [
                'value' => $value, 'error' => $e->getMessage(),
            ]);

            return $floor;
        }

        if ($parsed === null) {
            Log::warning('Tawked returned no expires_at — using the default window', [
                'value' => $value,
            ]);

            return $floor;
        }

        if ($parsed->lessThanOrEqualTo($floor)) {
            Log::info('Tawked expires_at was under our 5-minute floor — flooring it', [
                'value' => $value, 'parsed' => $parsed->toIso8601String(), 'now' => now()->toIso8601String(),
            ]);

            return $floor;
        }

        return $parsed;
    }
}
