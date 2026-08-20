{{--
    Renders an order's shipping/billing address JSON snapshot (see
    orders.shipping_address / billing_address). Two shapes exist:

    - New (location_code present): resolved via LocationLookupService at
      checkout — show the formatted address + district/city/region.
    - Legacy (no location_code): orders placed before the Saudi National
      Address cutover, still carrying the old free-text street/city/region/
      postal_code fields — shown as before so historical orders keep reading
      correctly forever.

    Used by checkout/confirmation, emails/order-confirmation, emails/order-
    shipped, orders/show, and admin/orders/show, so this branching logic
    lives in exactly one place.
--}}
@props(['address' => [], 'phone' => null, 'nameClass' => ''])

@php
    $address = is_array($address) ? $address : [];
    $isNewShape = !empty($address['location_code']);
@endphp

<div {{ $attributes }}>
    <div class="{{ $nameClass }}">{{ $address['recipient_name'] ?? '' }}</div>
    @if ($isNewShape)
        <div>{{ $address['formatted_address'] ?? '' }}</div>
        <div>
            @if (!empty($address['district'])){{ $address['district'] }}, @endif{{ $address['city'] ?? '' }}, {{ $address['region'] ?? '' }}
        </div>
    @else
        <div>{{ $address['street_address'] ?? '' }}</div>
        <div>{{ $address['city'] ?? '' }}, {{ $address['region'] ?? '' }} {{ $address['postal_code'] ?? '' }}</div>
    @endif
    <div>{{ $address['phone'] ?? $phone }}</div>
</div>
