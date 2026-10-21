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
 * NATIONAL_ADDRESS_BASE_URL / NATIONAL_ADDRESS_API_KEY are configured, a
 * format-valid code resolves via stubLookup() below — deliberately a SUCCESS
 * with deterministic canned (Riyadh) data, not a failure, so checkout and
 * account address-saving stay fully usable end to end during development.
 * Every call is logged as stub mode so nobody mistakes it for a real
 * resolution SERVER-side; the `is_stub` flag on the return value (see
 * lookup()'s docblock) is what lets callers surface that same fact to the
 * shopper/ops staff, since a stub success otherwise looks identical to a
 * real one. The real Http:: branch is wired against config and ready to
 * activate the moment real credentials are set — no other code in the app
 * needs to change, since both branches return the exact same
 * ['success','data','error','is_stub'] contract.
 *
 * That canned-success stub needs an explicit NATIONAL_ADDRESS_DEMO=true (see
 * stubAllowed()) — without it, an unconfigured lookup fails honestly, and
 * isAvailable() lets the address form hide the code method entirely. With it
 * on (deliberately allowed in production too, by request, until real
 * credentials exist), a real shopper's code DOES resolve to the fabricated
 * Riyadh address, with no demo indicator shown to them — the downstream
 * safety nets are what actually protect a real order: staff still see a
 * "Demo" badge on the order itself (address-summary.blade.php's own
 * is_stub check), and no real shipment can be created against it
 * (AramexFulfilmentService::consigneeFor() throws). The "Full Address"
 * manual-entry method is unaffected either way.
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

    /** stubLookup()'s formatted_address, which no real resolution produces. */
    private const STUB_FORMATTED_PATTERN = '/^Al Olaya, Riyadh, Riyadh Region \([A-Z]{4}\d{4}\)$/';

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
     * Whether an unconfigured lookup may fall back to canned demo data: only
     * with an explicit NATIONAL_ADDRESS_DEMO=true. Deliberately NOT gated on
     * APP_ENV — by request, this is allowed in production too until real
     * Saudi Post/SPL credentials are obtained. The real protection against a
     * demo address silently shipping is downstream, not here: see the class
     * docblock.
     */
    private function stubAllowed(): bool
    {
        return (bool) config('services.national_address.demo', false);
    }

    /** Whether a code lookup can succeed at all (real API or demo mode). */
    public function isAvailable(): bool
    {
        return $this->isConfigured() || $this->stubAllowed();
    }

    /**
     * Whether an address (order snapshot or saved address) holds the canned
     * demo resolution rather than a real one. Saved addresses don't keep the
     * is_stub flag, so the demo's own fixed signature is matched as well.
     *
     * @param array<string,mixed>|null $address
     */
    public static function isDemoAddress(?array $address): bool
    {
        if (! $address) {
            return false;
        }

        if (! empty($address['is_stub'])) {
            return true;
        }

        return preg_match(self::STUB_FORMATTED_PATTERN, (string) ($address['formatted_address'] ?? '')) === 1;
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
            if ($this->stubAllowed()) {
                return $this->stubLookup($normalized);
            }

            // Production traffic never sees fabricated data: no real
            // credentials means the shopper is told the lookup isn't
            // available right now (the same message an actual outage would
            // show) instead of silently getting someone else's Riyadh
            // address. They still have the "Full Address" method, which
            // works today regardless of this.
            Log::warning('National address lookup: no live credentials configured and demo mode off', [
                'code' => $normalized,
                'env'  => app()->environment(),
            ]);

            return ['success' => false, 'data' => null, 'error' => 'lookup_failed', 'is_stub' => false];
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
