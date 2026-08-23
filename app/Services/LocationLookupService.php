<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a Saudi National Address short code (AAAA1234) to coordinates +
 * address metadata (city, region, district, formatted address).
 *
 * No real Saudi Post / SPL National Address API credentials exist yet. Until
 * NATIONAL_ADDRESS_BASE_URL / NATIONAL_ADDRESS_API_KEY are configured, every
 * format-valid code resolves via stubLookup() below — deliberately a SUCCESS
 * with deterministic canned data, not a failure, so checkout and account
 * address-saving stay fully usable end to end today. Every call is logged as
 * stub mode so nobody mistakes it for a real resolution SERVER-side; the
 * `is_stub` flag on the return value (see lookup()'s docblock) is what lets
 * callers surface that same fact to the shopper/ops staff, since a stub
 * success otherwise looks identical to a real one. The real Http:: branch is
 * wired against config and ready to activate the moment real credentials are
 * set — no other code in the app needs to change, since both branches return
 * the exact same ['success','data','error','is_stub'] contract.
 *
 * The real endpoint path / auth scheme / response field names below are
 * UNVERIFIED against actual SPL API docs — no public, versioned SPL/Saudi
 * Post National Address API documentation could be found to confirm them
 * against. That's stated plainly rather than guessed past: enabling real
 * lookups requires (a) obtaining real Saudi Post/SPL National Address API
 * credentials — a business/registration step outside this codebase, not a
 * config flag to flip in isolation — and (b) once those arrive, verifying
 * the request/response shape below against whatever documentation ships
 * with them and correcting it if it differs.
 */
class LocationLookupService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    /** Magic code reserved for exercising the "not found" path without real credentials. */
    private const STUB_NOT_FOUND_CODE = 'ZZZZ0000';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.national_address.base_url', ''), '/');
        $this->apiKey  = (string) config('services.national_address.api_key', '');
        $this->timeout = (int) config('services.national_address.timeout', 10);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    /**
     * @return array{success: bool, data: ?array<string, mixed>, error: ?string, is_stub: bool}
     */
    public function lookup(string $code): array
    {
        $normalized = strtoupper(trim($code));

        if (preg_match('/^[A-Z]{4}\d{4}$/', $normalized) !== 1) {
            return ['success' => false, 'data' => null, 'error' => 'invalid_format', 'is_stub' => false];
        }

        if (! $this->isConfigured()) {
            return $this->stubLookup($normalized);
        }

        try {
            // STUB CONTRACT NOTICE: this call's URL, auth header, and the
            // response field paths below (`data.latitude`, etc.) are a
            // reasonable guess, not a verified SPL API contract — adjust
            // once real docs are in hand. The return shape this method
            // promises callers ('success'/'data'/'error'/'is_stub') must
            // not change.
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/addresses/{$normalized}");

            if ($response->status() === 404) {
                return ['success' => false, 'data' => null, 'error' => 'not_found', 'is_stub' => false];
            }

            if (! $response->successful()) {
                Log::warning('National address lookup failed', [
                    'code'   => $normalized,
                    'status' => $response->status(),
                ]);

                return ['success' => false, 'data' => null, 'error' => 'lookup_failed', 'is_stub' => false];
            }

            $body = $response->json();

            $latitude = data_get($body, 'data.latitude');
            $longitude = data_get($body, 'data.longitude');

            // A response with no coordinates isn't a partial success — it's
            // malformed. Coercing null to (float) 0.0 would silently store a
            // mid-Atlantic coordinate as if it were a real resolution, which
            // is a worse failure mode than an honest, loud "lookup_failed"
            // (the contract shape here is unverified against real SPL docs —
            // see the class docblock — so this guards against exactly that
            // kind of shape mismatch once real credentials are wired up).
            if ($latitude === null || $longitude === null) {
                Log::warning('National address lookup returned no coordinates', [
                    'code' => $normalized,
                    'body' => $body,
                ]);

                return ['success' => false, 'data' => null, 'error' => 'lookup_failed', 'is_stub' => false];
            }

            return [
                'success' => true,
                'data' => [
                    'latitude'          => (float) $latitude,
                    'longitude'         => (float) $longitude,
                    'city'              => (string) data_get($body, 'data.city', ''),
                    'region'            => (string) data_get($body, 'data.region', ''),
                    'district'          => (string) data_get($body, 'data.district', ''),
                    'country'           => (string) data_get($body, 'data.country', 'SA'),
                    'formatted_address' => (string) data_get($body, 'data.formatted_address', ''),
                ],
                'error' => null,
                'is_stub' => false,
            ];
        } catch (Exception $e) {
            Log::error('National address lookup error', ['code' => $normalized, 'error' => $e->getMessage()]);

            return ['success' => false, 'data' => null, 'error' => 'lookup_failed', 'is_stub' => false];
        }
    }

    /**
     * @return array{success: bool, data: ?array<string, mixed>, error: ?string, is_stub: bool}
     */
    private function stubLookup(string $normalizedCode): array
    {
        if ($normalizedCode === self::STUB_NOT_FOUND_CODE) {
            Log::info('National address lookup (stub mode): simulated not-found', ['code' => $normalizedCode]);

            return ['success' => false, 'data' => null, 'error' => 'not_found', 'is_stub' => true];
        }

        Log::info('National address lookup (stub mode): NATIONAL_ADDRESS_BASE_URL/API_KEY not set, returning canned data', [
            'code' => $normalizedCode,
        ]);

        return [
            'success' => true,
            'data' => [
                // Riyadh center-point — the same default already used by the
                // (now-retired) map picker in public/js/address-map.js.
                'latitude'          => 24.7136,
                'longitude'         => 46.6753,
                'city'              => 'Riyadh',
                'region'            => 'Riyadh Region',
                'district'          => 'Al Olaya',
                'country'           => 'SA',
                'formatted_address' => "Al Olaya, Riyadh, Riyadh Region ({$normalizedCode})",
            ],
            'error' => null,
            // Every non-invalid-format lookup hits this method until real
            // NATIONAL_ADDRESS_BASE_URL/API_KEY credentials are configured —
            // is_stub is what lets callers tell a demo resolution apart from
            // a real one instead of presenting canned data as if it were live.
            'is_stub' => true,
        ];
    }
}
