{{--
    Renders an order's shipping/billing address JSON snapshot, or a saved
    Address model's ->toArray() (see orders.shipping_address / billing_address,
    and Address::toArray()). Four shapes exist:

    - National Address code (method=national_code, or — for rows written
      before the `method` column existed — location_code present): resolved
      via LocationLookupService at checkout — show the formatted address +
      district/city/region.
    - Full Address (method=manual): typed by the shopper — show street +
      building/apartment, then district/city/postal code, then notes.
    - Pinned location (no method, no location_code, no street_address, but
      coordinates present): the retired map-pin method — coordinates only,
      no address text by design, so show a "Pinned location" label + a map
      link instead.
    - Legacy (no method, no location_code, no coordinates): orders placed
      before the Saudi National Address cutover, still carrying the old
      free-text street/city/region/postal_code fields — shown as before so
      historical orders keep reading correctly forever.

    The `method` key is checked first; when it's absent (any row written
    before this column existed) the branching falls back to the exact
    field-presence inference used before `method` existed, so no historical
    order or address changes appearance.

    Used by orders/show, admin/orders/show, checkout/confirmation, and
    checkout/order-review — NOT by emails/order-confirmation or
    emails/order-shipped, which are self-contained HTML documents (no
    Bootstrap/icon font loaded) and hand-roll their own copy of this same
    branching logic in plain HTML instead.
--}}
@props(['address' => [], 'phone' => null, 'nameClass' => ''])

@php
    $address = is_array($address) ? $address : [];
    $method = $address['method'] ?? null;
    $isManual = $method === \App\Models\Address::METHOD_MANUAL;
    $hasCode = $method === \App\Models\Address::METHOD_NATIONAL_CODE || (! $method && !empty($address['location_code']));
    $hasCoordinates = ! $method && !empty($address['latitude']) && !empty($address['longitude']);
@endphp

<div {{ $attributes }}>
    <div class="{{ $nameClass }}">{{ $address['recipient_name'] ?? '' }}</div>

    @if ($method)
        <span class="badge bg-light text-aroma-brown border mb-1">{{ __('location.method.'.($isManual ? 'manual' : 'code')) }}</span>
    @endif

    @if ($hasCode)
        <div>
            {{ $address['formatted_address'] ?? '' }}
            @if ($address['is_stub'] ?? false)
                {{-- LocationLookupService::stubLookup() — no real Saudi Post/SPL
                     credentials configured, so this address is canned demo data,
                     not a real resolution. Shown here so ops (admin/orders/show)
                     and the shopper both know not to treat it as verified. --}}
                <span class="badge bg-light text-aroma-brown border ms-1">{{ __('location.preview.demo_badge') }}</span>
            @endif
        </div>
        <div>
            @if (!empty($address['district'])){{ $address['district'] }}, @endif{{ $address['city'] ?? '' }}, {{ $address['region'] ?? '' }}
        </div>
    @elseif ($isManual)
        <div>
            {{ $address['street_address'] ?? '' }}
            @if (!empty($address['building_number'])) — {{ __('location.manual.building_short', ['number' => $address['building_number']]) }}@endif
            @if (!empty($address['apartment_number'])), {{ __('location.manual.apartment_short', ['number' => $address['apartment_number']]) }}@endif
        </div>
        <div>
            @if (!empty($address['district'])){{ $address['district'] }}, @endif{{ $address['city'] ?? '' }}
            @if (!empty($address['postal_code'])) {{ $address['postal_code'] }}@endif
        </div>
        @if (!empty($address['additional_notes']))
            <div class="small text-aroma-muted">{{ $address['additional_notes'] }}</div>
        @endif
    @elseif ($hasCoordinates)
        <div>
            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
            <a href="https://www.google.com/maps?q={{ $address['latitude'] }},{{ $address['longitude'] }}" target="_blank" rel="noopener">
                {{ __('location.map.pinned_label') }}
            </a>
        </div>
    @else
        <div>{{ $address['street_address'] ?? '' }}</div>
        <div>{{ $address['city'] ?? '' }}, {{ $address['region'] ?? '' }} {{ $address['postal_code'] ?? '' }}</div>
    @endif
    <div dir="ltr">{{ $address['phone'] ?? $phone }}</div>
</div>
