{{--
    Saudi National Address short code, resolved server-side to city/region/
    district/formatted_address/coordinates — see LocationLookupService.

    The map-pin method (Leaflet + free OSM search) has been removed for now;
    it's not accurate/complete enough without a paid geocoding API. The
    backend (resolveAddress()/AddressRequest) still accepts a lat/lng pair
    if one is ever submitted, so re-adding a picker later (Google Maps, once
    that's paid for) is a front-end-only change — see address-map.js /
    location-method-toggle.js / map-picker.css, kept but unloaded, for the
    previous implementation.
--}}
@props([
    'fieldPrefix' => '',      // e.g. 'billing_address', 'recipient' — '' for account's flat field names
    'domId' => 'location',    // unused now the toggle is gone; kept so callers don't need updating
    'code' => null,
    'latitude' => null,
    'longitude' => null,
])

@php
    $fieldName = fn (string $field) => $fieldPrefix ? "{$fieldPrefix}[{$field}]" : $field;
    $errorKey  = fn (string $field) => $fieldPrefix ? "{$fieldPrefix}.{$field}" : $field;
    $oldCode = old($errorKey('location_code'), $code);
@endphp

<div class="aroma-location-switcher">
    <div class="aroma-location-field">
        <input type="text" name="{{ $fieldName('location_code') }}"
               class="form-control js-location-code @error($errorKey('location_code')) is-invalid @enderror"
               value="{{ $oldCode }}" maxlength="8"
               placeholder="{{ __('location.placeholder') }}"
               data-lang-loading="{{ __('location.preview.loading') }}"
               data-lang-not-found="{{ __('location.errors.not_found') }}"
               data-lang-invalid="{{ __('location.errors.invalid_format') }}"
               data-lang-failed="{{ __('location.errors.lookup_failed') }}"
               data-lang-demo-badge="{{ __('location.preview.demo_badge') }}">
        <div class="aroma-location-preview" hidden></div>
    </div>
    <div class="form-text">{{ __('location.hint') }}</div>

    @error($errorKey('location_code'))
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
