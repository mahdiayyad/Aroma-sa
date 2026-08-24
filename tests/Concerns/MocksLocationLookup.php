<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Services\LocationLookupService;

/**
 * Binds a fake LocationLookupService so address-related tests never make a
 * real HTTP call. A couple of fixed codes resolve to distinct Riyadh/Jeddah
 * payloads (several existing fixtures assert on the city, so different
 * codes need to resolve to genuinely different cities); ZZZZ0000 simulates
 * a not-found lookup; any other well-formed code falls back to Riyadh.
 */
trait MocksLocationLookup
{
    protected function mockLocationLookup(): void
    {
        $this->mock(LocationLookupService::class, function ($mock) {
            $mock->shouldReceive('lookup')->andReturnUsing(function (string $code) {
                $normalized = strtoupper(trim($code));

                if ($normalized === 'ZZZZ0000') {
                    return ['success' => false, 'data' => null, 'error' => 'not_found', 'is_stub' => false];
                }

                $known = [
                    'RAHA1234' => ['city' => 'Riyadh', 'region' => 'Riyadh Region', 'district' => 'Al Olaya', 'latitude' => 24.7136, 'longitude' => 46.6753],
                    'JEDD5678' => ['city' => 'Jeddah', 'region' => 'Makkah Region', 'district' => 'Al Rawdah', 'latitude' => 21.5433, 'longitude' => 39.1728],
                ];

                $resolved = $known[$normalized] ?? $known['RAHA1234'];

                return [
                    'success' => true,
                    'data' => array_merge($resolved, [
                        'country' => 'SA',
                        'formatted_address' => $resolved['district'].', '.$resolved['city'].', '.$resolved['region'],
                    ]),
                    'error' => null,
                    // Matches LocationLookupService's real contract (see its
                    // lookup() docblock) — this fake resolves deterministic
                    // fixture data, not literal stub-mode canned data, so false.
                    'is_stub' => false,
                ];
            });
        });
    }
}
