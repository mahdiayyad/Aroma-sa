{{--
    Tamara promotional widget (product / cart / checkout). Tamara's widget-v2
    reads window.tamaraWidgetConfig once when its script loads, then upgrades
    every <tamara-widget> on the page; it needs the environment's PUBLIC key
    (sandbox key on sandbox, production key after Tamara approves testing).

    Language must match the page: the widget follows app()->getLocale(), the
    same locale the Tamara checkout session is opened in (see
    TamaraPaymentService::checkoutPayload).

    Renders nothing unless the public key is configured and Tamara is an
    enabled payment method, so a half-configured environment never shows a
    broken widget.

    Params: $amount (numeric, SAR), $live (optional) — 'pdp' | 'cart' marks the
    element so the page's own script can keep its amount in sync as the
    quantity / variant / cart changes.
--}}
@php
    $tamaraPublicKey = config('services.tamara.public_key');
    $tamaraAmount    = (float) ($amount ?? 0);
    $tamaraWidgetUrl = config('services.tamara.widget_url')
        ?: (strpos((string) config('services.tamara.base_url'), 'sandbox') !== false
            ? 'https://cdn-sandbox.tamara.co/widget-v2/tamara-widget.js'
            : 'https://cdn.tamara.co/widget-v2/tamara-widget.js');
@endphp

@if ($tamaraPublicKey && $tamaraAmount > 0 && in_array('tamara', config('aroma.payments.methods', []), true))
    <div class="aroma-tamara-widget mb-3">
        <tamara-widget type="tamara-summary"
                       amount="{{ number_format($tamaraAmount, 2, '.', '') }}"
                       @if (! empty($live)) data-live-{{ $live }} @endif></tamara-widget>
    </div>

    @once
        @push('scripts')
            <script>
                window.tamaraWidgetConfig = {
                    lang: @json(app()->getLocale()),
                    country: 'SA',
                    publicKey: @json($tamaraPublicKey)
                };
            </script>
            <script src="{{ $tamaraWidgetUrl }}" async></script>
        @endpush
    @endonce
@endif
