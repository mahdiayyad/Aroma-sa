<?php

namespace Tests\Unit;

use App\Services\LocationLookupService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LocationLookupServiceTest extends TestCase
{
    public function test_malformed_code_fails_without_making_an_http_call(): void
    {
        Http::fake();
        config(['services.national_address.base_url' => 'https://na.test', 'services.national_address.api_key' => 'key']);

        $result = (new LocationLookupService())->lookup('1234RAHA');

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_format', $result['error']);
        Http::assertNothingSent();
    }

    public function test_unconfigured_service_returns_a_deterministic_stub_success(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
        $this->assertArrayHasKey('latitude', $result['data']);
        $this->assertArrayHasKey('longitude', $result['data']);
        $this->assertArrayHasKey('city', $result['data']);
        $this->assertArrayHasKey('region', $result['data']);
        $this->assertArrayHasKey('district', $result['data']);
        $this->assertArrayHasKey('country', $result['data']);
        $this->assertArrayHasKey('formatted_address', $result['data']);
        $this->assertTrue($result['is_stub'], 'stub-mode success must be flagged so callers can show a demo-data indicator');
    }

    public function test_unconfigured_service_stub_simulates_not_found_for_the_magic_code(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);

        $result = (new LocationLookupService())->lookup('ZZZZ0000');

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['error']);
        $this->assertTrue($result['is_stub']);
    }

    public function test_unconfigured_service_in_staging_still_gets_the_stub(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);
        app()->instance('env', 'staging');

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_stub']);
    }

    /**
     * The production-safety guarantee this whole class exists for: without
     * real credentials, a real shopper must get an honest failure, never a
     * fabricated Riyadh address recorded against their order.
     */
    public function test_unconfigured_service_in_production_fails_instead_of_fabricating_an_address(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);
        app()->instance('env', 'production');

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertSame('lookup_failed', $result['error']);
        $this->assertFalse($result['is_stub'], 'a failure is never flagged as stub data — there is no data at all');
    }

    public function test_unconfigured_service_in_an_unrecognised_environment_also_fails_closed(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);
        app()->instance('env', 'review-app-42'); // e.g. an unforeseen preview-deployment env name

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertFalse($result['success'], 'an unrecognised environment must not be treated as safe for demo data');
    }

    public function test_configured_service_maps_a_successful_response(): void
    {
        config(['services.national_address.base_url' => 'https://na.test', 'services.national_address.api_key' => 'key']);

        Http::fake([
            'na.test/*' => Http::response(['data' => [
                'latitude' => 24.71,
                'longitude' => 46.67,
                'city' => 'Riyadh',
                'region' => 'Riyadh Region',
                'district' => 'Al Olaya',
                'country' => 'SA',
                'formatted_address' => 'Al Olaya, Riyadh',
            ]], 200),
        ]);

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertTrue($result['success']);
        $this->assertSame('Riyadh', $result['data']['city']);
        $this->assertSame(24.71, $result['data']['latitude']);
        $this->assertFalse($result['is_stub'], 'a real, configured lookup must never be flagged as stub data');
    }

    /**
     * A malformed real-API response (no coordinates) must fail loudly, not
     * silently coerce to (float) 0.0 — a mid-Atlantic coordinate that would
     * otherwise pass through as if it were a genuine resolution.
     */
    public function test_configured_service_treats_missing_coordinates_as_a_failure(): void
    {
        config(['services.national_address.base_url' => 'https://na.test', 'services.national_address.api_key' => 'key']);
        Http::fake(['na.test/*' => Http::response(['data' => [
            'city' => 'Riyadh',
            // latitude/longitude deliberately absent
        ]], 200)]);
        Log::spy();

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertFalse($result['success']);
        $this->assertSame('lookup_failed', $result['error']);
        $this->assertFalse($result['is_stub']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_configured_service_reports_not_found_on_a_404(): void
    {
        config(['services.national_address.base_url' => 'https://na.test', 'services.national_address.api_key' => 'key']);
        Http::fake(['na.test/*' => Http::response([], 404)]);

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['error']);
        $this->assertFalse($result['is_stub']);
    }

    public function test_configured_service_reports_lookup_failed_on_a_server_error_and_logs_it(): void
    {
        config(['services.national_address.base_url' => 'https://na.test', 'services.national_address.api_key' => 'key']);
        Http::fake(['na.test/*' => Http::response([], 500)]);
        Log::spy();

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertFalse($result['success']);
        $this->assertSame('lookup_failed', $result['error']);
        Log::shouldHaveReceived('warning')->once();
    }
}
