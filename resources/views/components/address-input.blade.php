{{--
    Two address methods, chosen via a segmented toggle: a Saudi National
    Address short code (resolved server-side to city/region/district/
    formatted_address/coordinates — see LocationLookupService), or a fully
    manual "Full Address" entry (country/city/district/street/building/
    apartment/postal code/notes).

    Both panels stay in the DOM at all times — only `d-none` toggles which is
    visible (see public/js/address-method-toggle.js) — so switching between
    methods never clears what the shopper already typed.

    A retired third method (a Leaflet map pin) was fully built and then
    unwired from every view because it wasn't accurate enough without a paid
    geocoding API. The backend (AddressResolver/AddressRequest) still accepts
    a lat/lng pair if one is ever submitted — see address-map.js /
    location-method-toggle.js / map-picker.css, kept but unloaded — but no UI
    here re-exposes it; this component offers exactly the two methods above.
--}}
@props([
    'fieldPrefix' => '',      // e.g. 'billing_address', 'recipient' — '' for account's flat field names
    'domId' => 'location',
    'method' => null,        // Address::METHOD_NATIONAL_CODE | Address::METHOD_MANUAL | null (defaults to national_code)
    'code' => null,
    'latitude' => null,
    'longitude' => null,
    'country' => 'SA',
    'city' => null,
    'district' => null,
    'street' => null,
    'buildingNumber' => null,
    'apartmentNumber' => null,
    'postalCode' => null,
    'additionalNotes' => null,
])

@php
    $fieldName = fn (string $field) => $fieldPrefix ? "{$fieldPrefix}[{$field}]" : $field;
    $errorKey  = fn (string $field) => $fieldPrefix ? "{$fieldPrefix}.{$field}" : $field;

    $oldMethod = old($errorKey('method'), $method ?: \App\Models\Address::METHOD_NATIONAL_CODE);
    $isManual = $oldMethod === \App\Models\Address::METHOD_MANUAL;
    $oldCode = old($errorKey('location_code'), $code);
    $uid = $domId . '-' . \Illuminate\Support\Str::random(6);
@endphp

<div class="aroma-address-input" data-address-input>
    <div class="aroma-segmented">
        <input type="radio" class="btn-check" name="{{ $fieldName('method') }}" id="{{ $uid }}-code"
               value="{{ \App\Models\Address::METHOD_NATIONAL_CODE }}" data-method="{{ \App\Models\Address::METHOD_NATIONAL_CODE }}"
               {{ ! $isManual ? 'checked' : '' }}>
        <label class="aroma-segmented-option" for="{{ $uid }}-code">
            <i class="bi bi-upc-scan" aria-hidden="true"></i><span>{{ __('location.method.code') }}</span>
        </label>

        <input type="radio" class="btn-check" name="{{ $fieldName('method') }}" id="{{ $uid }}-manual"
               value="{{ \App\Models\Address::METHOD_MANUAL }}" data-method="{{ \App\Models\Address::METHOD_MANUAL }}"
               {{ $isManual ? 'checked' : '' }}>
        <label class="aroma-segmented-option" for="{{ $uid }}-manual">
            <i class="bi bi-geo-alt" aria-hidden="true"></i><span>{{ __('location.method.manual') }}</span>
        </label>
    </div>

    {{-- Panel 1: National Address code --}}
    <div class="aroma-address-panel {{ $isManual ? 'd-none' : '' }}" data-method-panel="{{ \App\Models\Address::METHOD_NATIONAL_CODE }}">
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

    {{-- Panel 2: Full Address --}}
    <div class="aroma-address-panel {{ $isManual ? '' : 'd-none' }}" data-method-panel="{{ \App\Models\Address::METHOD_MANUAL }}">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">{{ __('location.manual.country') }}</label>
                <select class="form-select" disabled>
                    <option selected>{{ __('location.manual.country_sa') }}</option>
                </select>
                <input type="hidden" name="{{ $fieldName('country') }}" value="{{ old($errorKey('country'), $country ?: 'SA') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">{{ __('location.manual.city') }}</label>
                <input type="text" name="{{ $fieldName('city') }}" value="{{ old($errorKey('city'), $city) }}"
                       class="form-control @error($errorKey('city')) is-invalid @enderror">
                @error($errorKey('city'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">{{ __('location.manual.district') }}</label>
                <input type="text" name="{{ $fieldName('district') }}" value="{{ old($errorKey('district'), $district) }}"
                       class="form-control @error($errorKey('district')) is-invalid @enderror">
                @error($errorKey('district'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">{{ __('location.manual.street') }}</label>
                <input type="text" name="{{ $fieldName('street_address') }}" value="{{ old($errorKey('street_address'), $street) }}"
                       class="form-control @error($errorKey('street_address')) is-invalid @enderror">
                @error($errorKey('street_address'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">{{ __('location.manual.building_number') }}</label>
                <input type="text" name="{{ $fieldName('building_number') }}" value="{{ old($errorKey('building_number'), $buildingNumber) }}"
                       class="form-control @error($errorKey('building_number')) is-invalid @enderror">
                @error($errorKey('building_number'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">{{ __('location.manual.apartment_number') }}</label>
                <input type="text" name="{{ $fieldName('apartment_number') }}" value="{{ old($errorKey('apartment_number'), $apartmentNumber) }}"
                       class="form-control @error($errorKey('apartment_number')) is-invalid @enderror">
                @error($errorKey('apartment_number'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">{{ __('location.manual.postal_code') }}</label>
                <input type="text" name="{{ $fieldName('postal_code') }}" value="{{ old($errorKey('postal_code'), $postalCode) }}"
                       class="form-control @error($errorKey('postal_code')) is-invalid @enderror">
                @error($errorKey('postal_code'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">{{ __('location.manual.additional_notes') }}</label>
                <textarea name="{{ $fieldName('additional_notes') }}" rows="2" maxlength="500"
                          class="form-control @error($errorKey('additional_notes')) is-invalid @enderror">{{ old($errorKey('additional_notes'), $additionalNotes) }}</textarea>
                @error($errorKey('additional_notes'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
