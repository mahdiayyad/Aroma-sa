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
 * stub mode so nobody mistakes it for a real resolution. The real Http::
 * branch is wired against config and ready to activate the moment real
 * credentials are set — no other code in the app needs to change, since both
 * branches return the exact same ['success','data','error'] contract.
 *
 * The real endpoint path / auth scheme / response field names below are
 * UNVERIFIED against actual SPL API docs (none were available) and will need
 * adjusting once real credentials + documentation exist.
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
     * @return array{success: bool, data: ?array<string, mixed>, error: ?string}
     */
    public function lookup(string $code): array
    {
        $normalized = strtoupper(trim($code));

        if (preg_match('/^[A-Z]{4}\d{4}$/', $normalized) !== 1) {
            return ['success' => false, 'data' => null, 'error' => 'invalid_format'];
        }

        if (! $this->isConfigured()) {
            return $this->stubLookup($normalized);
        }

        try {
            // STUB CONTRACT NOTICE: this call's URL, auth header, and the
            // response field paths below (`data.latitude`, etc.) are a
            // reasonable guess, not a verified SPL API contract — adjust
            // once real docs are in hand. The return shape this method
            // promises callers ('success'/'data'/'error') must not change.
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/addresses/{$normalized}");

            if ($response->status() === 404) {
                return ['success' => false, 'data' => null, 'error' => 'not_found'];
            }

            if (! $response->successful()) {
                Log::warning('National address lookup failed', [
                    'code'   => $normalized,
                    'status' => $response->status(),
                ]);

                return ['success' => false, 'data' => null, 'error' => 'lookup_failed'];
            }

            $body = $response->json();

            return [
                'success' => true,
                'data' => [
                    'latitude'          => (float) data_get($body, 'data.latitude'),
                    'longitude'         => (float) data_get($body, 'data.longitude'),
                    'city'              => (string) data_get($body, 'data.city', ''),
                    'region'            => (string) data_get($body, 'data.region', ''),
                    'district'          => (string) data_get($body, 'data.district', ''),
                    'country'           => (string) data_get($body, 'data.country', 'SA'),
                    'formatted_address' => (string) data_get($body, 'data.formatted_address', ''),
                ],
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::error('National address lookup error', ['code' => $normalized, 'error' => $e->getMessage()]);

            return ['success' => false, 'data' => null, 'error' => 'lookup_failed'];
        }
    }

    /**
     * @return array{success: bool, data: ?array<string, mixed>, error: ?string}
     */
    private function stubLookup(string $normalizedCode): array
    {
        if ($normalizedCode === self::STUB_NOT_FOUND_CODE) {
            Log::info('National address lookup (stub mode): simulated not-found', ['code' => $normalizedCode]);

            return ['success' => false, 'data' => null, 'error' => 'not_found'];
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
        ];
    }
}
