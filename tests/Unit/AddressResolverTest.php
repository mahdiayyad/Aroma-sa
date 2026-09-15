<?php

namespace Tests\Unit;

use App\Models\Address;
use App\Services\AddressResolver;
use App\Services\LocationLookupService;
use Tests\TestCase;

class AddressResolverTest extends TestCase
{
    private function resolver(): AddressResolver
    {
        return new AddressResolver(new LocationLookupService());
    }

    public function test_a_national_code_resolves_via_the_lookup_service(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);

        $result = $this->resolver()->resolve(
            ['recipient_name' => 'Sara', 'phone' => '+966500000000'],
            Address::METHOD_NATIONAL_CODE,
            'RAHA1234',
            null,
            null
        );

        $this->assertNotNull($result);
        $this->assertSame(Address::METHOD_NATIONAL_CODE, $result['method']);
        $this->assertSame('RAHA1234', $result['location_code']);
        $this->assertNotEmpty($result['city']);
        $this->assertNull($result['street_address']);
        $this->assertNull($result['building_number']);
        $this->assertNull($result['apartment_number']);
        $this->assertArrayHasKey('is_stub', $result);
    }

    public function test_a_national_code_lookup_failure_returns_null(): void
    {
        config(['services.national_address.base_url' => '', 'services.national_address.api_key' => '']);

        // ZZZZ0000 is LocationLookupService's reserved magic "not found" code.
        $result = $this->resolver()->resolve(
            ['recipient_name' => 'Sara', 'phone' => '+966500000000'],
            Address::METHOD_NATIONAL_CODE,
            'ZZZZ0000',
            null,
            null
        );

        $this->assertNull($result);
    }

    public function test_a_manual_address_maps_fields_and_builds_a_formatted_address(): void
    {
        $result = $this->resolver()->resolve(
            ['recipient_name' => 'Sara', 'phone' => '+966500000000'],
            Address::METHOD_MANUAL,
            null,
            null,
            null,
            [
                'country' => 'SA',
                'city' => 'Jeddah',
                'district' => 'Al Rawdah',
                'street_address' => 'King Fahd Road',
                'building_number' => '1234',
                'apartment_number' => '5',
                'postal_code' => '23432',
                'additional_notes' => 'Near the mosque',
            ]
        );

        $this->assertNotNull($result);
        $this->assertSame(Address::METHOD_MANUAL, $result['method']);
        $this->assertNull($result['location_code']);
        $this->assertNull($result['latitude']);
        $this->assertNull($result['longitude']);
        $this->assertSame('Jeddah', $result['city']);
        $this->assertSame('1234', $result['building_number']);
        $this->assertSame('5', $result['apartment_number']);
        $this->assertSame('23432', $result['postal_code']);
        $this->assertSame('Near the mosque', $result['additional_notes']);
        $this->assertFalse($result['is_stub']);

        // Server-built, never trusts a client-submitted formatted_address.
        $this->assertStringContainsString('King Fahd Road', $result['formatted_address']);
        $this->assertStringContainsString('Jeddah', $result['formatted_address']);
    }

    public function test_a_bare_coordinate_pin_with_no_method_stays_behaviour_equivalent_to_the_legacy_path(): void
    {
        $result = $this->resolver()->resolve(
            ['recipient_name' => 'Sara', 'phone' => '+966500000000'],
            null,
            null,
            24.7136,
            46.6753
        );

        $this->assertNotNull($result);
        $this->assertNull($result['method']);
        $this->assertNull($result['location_code']);
        $this->assertSame(24.7136, $result['latitude']);
        $this->assertSame(46.6753, $result['longitude']);
        $this->assertNull($result['city']);
        $this->assertNull($result['formatted_address']);
        $this->assertFalse($result['is_stub']);
    }
}
