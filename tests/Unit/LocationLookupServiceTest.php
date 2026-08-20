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
    }

    public function test_unconfigured_service_stub_simulates_not_found_for_the_magic_code(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);

        $result = (new LocationLookupService())->lookup('ZZZZ0000');

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['error']);
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
    }

    public function test_configured_service_reports_not_found_on_a_404(): void
    {
        config(['services.national_address.base_url' => 'https://na.test', 'services.national_address.api_key' => 'key']);
        Http::fake(['na.test/*' => Http::response([], 404)]);

        $result = (new LocationLookupService())->lookup('RAHA1234');

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['error']);
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
