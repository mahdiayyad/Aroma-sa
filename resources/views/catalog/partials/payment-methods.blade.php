{{-- طرق الدفع — trust badges on the product page. A method is only shown as
     available when its gateway is actually configured, so we never promise a
     payment option that would fail at checkout (BNPL shows as "soon"). --}}
@php($payReady = [
    'moyasar' => (bool) config('services.moyasar.secret_key'),
    'tabby'   => (bool) (config('services.tabby.secret_key') && config('services.tabby.merchant_code')),
    'tamara'  => (bool) config('services.tamara.api_token'),
])

<section class="aroma-payments" aria-labelledby="aromaPaymentsTitle">
    <h2 class="aroma-payments-title" id="aromaPaymentsTitle">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>{{ __('storefront.payment.title') }}
    </h2>

    <ul class="aroma-payment-list">
        @foreach (config('aroma.payments.methods', []) as $method)
            @php($isBnpl = in_array($method, config('aroma.payments.bnpl', []), true))
            @php($ready = $payReady[$isBnpl ? $method : 'moyasar'] ?? false)
            <li class="aroma-payment-badge {{ $ready ? '' : 'is-soon' }}">
                <x-payment-icon :method="$method" />
                <span>{{ __('checkout.payment_methods.'.strtolower($method)) }}</span>
                @unless ($ready)<span class="aroma-payment-soon">{{ __('storefront.payment.soon') }}</span>@endunless
            </li>
        @endforeach
    </ul>

    <p class="aroma-payments-note">{{ __('storefront.payment.note') }}</p>
</section>
