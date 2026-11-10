{{-- E-commerce events for this page (see App\Services\TrackingService and public/js/aroma-tracking.js). --}}
@php($tracking = app(\App\Services\TrackingService::class)->drain())
@if (\App\Support\MarketingPixels::enabled())
    <script type="application/json" id="aromaTrackingData">@json($tracking)</script>
    <script src="{{ \App\Support\Assets::versioned('js/aroma-tracking.js') }}"></script>
@endif
