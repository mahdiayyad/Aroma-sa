{{--
    Two ways to give a location, never both stored at once:
    - "code": Saudi National Address short code, resolved server-side to
      city/region/district/formatted_address/coordinates.
    - "map": a pinned coordinate pair only — no lookup, no address text.
      Delivery for these is coordinate-based (Aramex/other carrier), not a
      written address.

    location-method-toggle.js switches between the two panels and clears
    whichever one isn't active, so only one method's inputs are ever
    submitted. address-map.js drives the Leaflet picker inside the map panel.

    NOTE: the map panel's internal elements (#addressMap, #addressLatitude,
    etc.) use fixed ids, matching address-map.js's getElementById lookups —
    this component is only ever rendered once per page today (billing OR
    recipient OR the account form, never two at once). If a future page ever
    needs two location-pickers simultaneously, address-map.js would need to
    move to a class-based, multi-instance pattern like location-lookup.js.
--}}
@props([
    'fieldPrefix' => '',      // e.g. 'billing_address', 'recipient' — '' for account's flat field names
    'domId' => 'location',    // unique per-instance id/name prefix for the toggle radios
    'code' => null,
    'latitude' => null,
    'longitude' => null,
])

@php
    $fieldName = fn (string $field) => $fieldPrefix ? "{$fieldPrefix}[{$field}]" : $field;
    $errorKey  = fn (string $field) => $fieldPrefix ? "{$fieldPrefix}.{$field}" : $field;
    $oldCode = old($errorKey('location_code'), $code);
    $oldLat  = old($errorKey('latitude'), $latitude);
    $oldLng  = old($errorKey('longitude'), $longitude);
    $initialMethod = ($oldLat !== null && $oldLat !== '') ? 'map' : 'code';
@endphp

<div class="aroma-location-switcher">
    <div class="aroma-location-toggle">
        <input type="radio" class="btn-check" name="{{ $domId }}_location_method" id="{{ $domId }}MethodCode"
               value="code" data-method="code" {{ $initialMethod === 'code' ? 'checked' : '' }}>
        <label class="aroma-location-toggle-option" for="{{ $domId }}MethodCode">
            <i class="bi bi-upc-scan"></i><span>{{ __('location.method.code') }}</span>
        </label>

        <input type="radio" class="btn-check" name="{{ $domId }}_location_method" id="{{ $domId }}MethodMap"
               value="map" data-method="map" {{ $initialMethod === 'map' ? 'checked' : '' }}>
        <label class="aroma-location-toggle-option" for="{{ $domId }}MethodMap">
            <i class="bi bi-geo-alt"></i><span>{{ __('location.method.map') }}</span>
        </label>
    </div>

    <div class="js-method-panel {{ $initialMethod === 'code' ? '' : 'd-none' }}" data-method="code">
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
    </div>

    <div class="js-method-panel {{ $initialMethod === 'map' ? '' : 'd-none' }}" data-method="map">
        <p class="form-text mt-0 mb-2">{{ __('location.map.hint') }}</p>
        <div class="aroma-map-picker">
            <div class="aroma-map-search">
                <div class="input-group">
                    <input type="text" id="addressMapSearch" class="form-control" autocomplete="off"
                           placeholder="{{ __('location.map.search_placeholder') }}"
                           data-lang-loading="{{ __('location.map.search_loading') }}"
                           data-lang-no-results="{{ __('location.map.search_no_results') }}"
                           data-lang-error="{{ __('location.map.search_error') }}"
                           data-lang-rate-limited="{{ __('location.map.search_rate_limited') }}"
                           data-lang-retry="{{ __('location.map.search_retry') }}">
                    <button type="button" id="addressMapLocateBtn" class="btn btn-aroma-outline"
                            data-bs-toggle="tooltip" data-bs-placement="top"
                            title="{{ __('location.map.use_current') }}">
                        <i class="bi bi-crosshair"></i>
                    </button>
                </div>
                <div id="addressMapSearchResults" class="aroma-map-search-results d-none"></div>
            </div>

            <div class="aroma-map-canvas-wrap">
                <div id="addressMap" class="aroma-map-canvas" tabindex="0"
                     data-lat="{{ $oldLat }}" data-lng="{{ $oldLng }}"
                     data-mapbox-token="{{ config('services.mapbox.access_token') }}"></div>
                <div id="addressMapHint" class="aroma-map-hint">
                    <i class="bi bi-hand-index-thumb"></i>
                    {{ __('location.map.scroll_hint') }}
                </div>
            </div>

            <div id="addressMapDetected" class="aroma-map-detected d-none">
                <i class="bi bi-geo-alt-fill"></i>
                <span id="addressMapDetectedText" class="flex-grow-1"></span>
            </div>
        </div>

        <input type="hidden" name="{{ $fieldName('latitude') }}" id="addressLatitude" value="{{ $oldLat }}">
        <input type="hidden" name="{{ $fieldName('longitude') }}" id="addressLongitude" value="{{ $oldLng }}">
        @error($errorKey('latitude'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    @error($errorKey('location_code'))
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
