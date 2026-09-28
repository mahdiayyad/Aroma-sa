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

    private function parseExpiry($value): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : now()->addMinutes(5);
        } catch (Throwable $e) {
            return now()->addMinutes(5);
        }
    }
}
