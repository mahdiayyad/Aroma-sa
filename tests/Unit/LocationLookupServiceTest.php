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

    private function unconfigured(bool $demo): void
    {
        config([
            'services.national_address.base_url' => '',
            'services.national_address.api_key' => '',
            'services.national_address.demo' => $demo,
        ]);
    }

    public function test_demo_mode_returns_a_deterministic_stub_success(): void
    {
        $this->unconfigured(true);

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
        foreach (['latitude', 'longitude', 'city', 'region', 'district', 'country', 'formatted_address'] as $key) {
            $this->assertArrayHasKey($key, $result['data']);
        }
        $this->assertTrue($result['is_stub'], 'stub-mode success must be flagged so callers can show a demo-data indicator');
    }

    public function test_demo_mode_simulates_not_found_for_the_magic_code(): void
    {
        $this->unconfigured(true);

        $result = (new LocationLookupService())->lookup('ZZZZ0000');

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['error']);
        $this->assertTrue($result['is_stub']);
    }

    public function test_demo_mode_works_on_staging(): void
    {
        $this->unconfigured(true);
        app()->instance('env', 'staging');

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_stub']);
    }

    /**
     * What the live server actually did: APP_ENV=local, no real API, and the
     * demo Riyadh address served to real shoppers. Without the explicit
     * opt-in, no environment gets demo data.
     */
    public function test_without_the_demo_opt_in_even_a_local_environment_gets_no_demo_data(): void
    {
        $this->unconfigured(false);
        app()->instance('env', 'local');

        $service = new LocationLookupService();
        $result = $service->lookup('RAHA1234');

        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertSame('lookup_failed', $result['error']);
        $this->assertFalse($result['is_stub'], 'a failure is never flagged as stub data — there is no data at all');
        $this->assertFalse($service->isAvailable());
    }

    public function test_production_never_gets_demo_data_even_with_the_opt_in(): void
    {
        $this->unconfigured(true);
        app()->instance('env', 'production');

        $service = new LocationLookupService();

        $this->assertFalse($service->lookup('RAHA1234')['success']);
        $this->assertFalse($service->isAvailable());
    }

    public function test_a_configured_service_is_available_without_demo_mode(): void
    {
        config([
            'services.national_address.base_url' => 'https://na.test',
            'services.national_address.api_key' => 'key',
            'services.national_address.demo' => false,
        ]);
        app()->instance('env', 'production');

        $this->assertTrue((new LocationLookupService())->isAvailable());
    }

    public function test_demo_addresses_are_recognised_with_or_without_the_flag(): void
    {
        $this->unconfigured(true);
        $demo = (new LocationLookupService())->lookup('ABCD1234')['data'];

        $this->assertTrue(LocationLookupService::isDemoAddress(['is_stub' => true]));
        $this->assertTrue(LocationLookupService::isDemoAddress($demo), 'a saved address keeps the demo signature but not the flag');
        $this->assertFalse(LocationLookupService::isDemoAddress(['formatted_address' => 'Al Olaya, Riyadh', 'city' => 'Riyadh']));
        $this->assertFalse(LocationLookupService::isDemoAddress(['formatted_address' => 'Tahlia St, Bldg 7, Al Rawdah, Jeddah']));
        $this->assertFalse(LocationLookupService::isDemoAddress(null));
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
