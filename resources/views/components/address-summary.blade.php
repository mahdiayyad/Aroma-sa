{{--
    Renders an order's shipping/billing address JSON snapshot (see
    orders.shipping_address / billing_address). Three shapes exist:

    - Location code (location_code present): resolved via
      LocationLookupService at checkout — show the formatted address +
      district/city/region.
    - Pinned location (no location_code, no street_address, but coordinates
      present): the map-pin method — coordinates only, no address text by
      design, so show a "Pinned location" label + a map link instead.
    - Legacy (no location_code, no coordinates): orders placed before the
      Saudi National Address cutover, still carrying the old free-text
      street/city/region/postal_code fields — shown as before so historical
      orders keep reading correctly forever.

    Used by checkout/confirmation, checkout/order-review, emails/order-
    confirmation, emails/order-shipped, orders/show, and admin/orders/show,
    so this branching logic lives in exactly one place.
--}}
@props(['address' => [], 'phone' => null, 'nameClass' => ''])

@php
    $address = is_array($address) ? $address : [];
    $hasCode = !empty($address['location_code']);
    $hasCoordinates = !empty($address['latitude']) && !empty($address['longitude']);
@endphp

<div {{ $attributes }}>
    <div class="{{ $nameClass }}">{{ $address['recipient_name'] ?? '' }}</div>
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
