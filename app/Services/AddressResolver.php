<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;

/**
 * Turns validated address input — one of a National Address short code, a
 * manually-typed set of fields, or (legacy/defensive only, no longer
 * reachable from any UI) a bare lat/lng pin — into the normalized snapshot
 * array stored in the checkout session / order JSON columns, or merged into
 * an Address model's attributes before persistence. Single home for logic
 * that used to be hand-duplicated in CheckoutController::resolveAddress()
 * and Account\AddressController::payload().
 *
 * $contact is merged into every result as-is: callers building a checkout
 * session snapshot pass just recipient_name/phone/email, while
 * Account\AddressController passes its whole validated payload (label,
 * is_default, type, recipient_name, phone, ...) so persistence-only fields
 * survive untouched alongside the resolved location fields.
 */
class AddressResolver
{
    private LocationLookupService $locationLookup;

    public function __construct(LocationLookupService $locationLookup)
    {
        $this->locationLookup = $locationLookup;
    }

    /**
     * @param array<string,mixed> $contact
     * @param array<string,mixed> $manual Only read when $method === Address::METHOD_MANUAL.
     *   Keys: country, city, district, street_address, building_number,
     *   apartment_number, postal_code, additional_notes.
     * @return array<string,mixed>|null Every key is always present (even if null). Null only
     *   when resolving a National Address code and the lookup fails — callers turn that into
     *   the existing field-specific validation error, same as today.
     */
    public function resolve(
        array $contact,
        ?string $method,
        ?string $locationCode,
        ?float $latitude,
        ?float $longitude,
        array $manual = []
    ): ?array {
        if ($method === Address::METHOD_MANUAL) {
            return $this->resolveManual($contact, $manual);
        }

        if ($method === Address::METHOD_NATIONAL_CODE || ($method === null && filled($locationCode))) {
            return $this->resolveNationalCode($contact, (string) $locationCode);
        }

        // Legacy/defensive fallback: a bare lat/lng pin with no method named.
        // No current UI can reach this (the map picker was retired before
        // this feature existed) — kept so resolve() stays a strict superset
        // of the old resolveAddress()/payload() branches, byte-for-byte
        // equivalent to their previous "no code" behavior.
        return array_merge($contact, [
            'method' => null,
            'location_code' => null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'city' => null,
            'region' => null,
            'district' => null,
            'country' => null,
            'formatted_address' => null,
            'street_address' => null,
            'building_number' => null,
            'apartment_number' => null,
            'postal_code' => null,
            'additional_notes' => null,
            'is_stub' => false,
        ]);
    }

    private function resolveNationalCode(array $contact, string $locationCode): ?array
    {
        $result = $this->locationLookup->lookup($locationCode);

        if (! $result['success']) {
            return null;
        }

        return array_merge($contact, [
            'method' => Address::METHOD_NATIONAL_CODE,
            'location_code' => strtoupper(trim($locationCode)),
            'street_address' => null,
            'building_number' => null,
            'apartment_number' => null,
            'postal_code' => null,
            'additional_notes' => null,
        ], $result['data'], ['is_stub' => $result['is_stub'] ?? false]);
    }

    private function resolveManual(array $contact, array $manual): array
    {
        return array_merge($contact, [
            'method' => Address::METHOD_MANUAL,
            'location_code' => null,
            'latitude' => null,
            'longitude' => null,
            'country' => $manual['country'] ?? 'SA',
            'city' => $manual['city'] ?? null,
            'district' => $manual['district'] ?? null,
            'street_address' => $manual['street_address'] ?? null,
            'building_number' => $manual['building_number'] ?? null,
            'apartment_number' => $manual['apartment_number'] ?? null,
            'postal_code' => $manual['postal_code'] ?? null,
            'additional_notes' => $manual['additional_notes'] ?? null,
            'formatted_address' => $this->buildFormattedAddress($manual),
            'is_stub' => false,
        ]);
    }

    /** Server-built, consistent display string — never trusts a client-submitted formatted_address. */
    private function buildFormattedAddress(array $manual): ?string
    {
        $streetLine = trim(implode(' ', array_filter([
            $manual['street_address'] ?? null,
            filled($manual['building_number'] ?? null)
                ? __('location.manual.building_short', ['number' => $manual['building_number']])
                : null,
        ])));

        $parts = array_filter([
            $streetLine ?: null,
            $manual['district'] ?? null,
            $manual['city'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
