@extends('layouts.app')

@section('title', __('checkout.payment').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
@php($locale = app()->getLocale())
{{-- pages.partials.terms-content (included by #termsModal below) needs $isAr. --}}
@php($isAr = $locale === 'ar')
<div class="container checkout-page my-4 my-lg-5">
    @include('checkout.partials.stepper', ['step' => 6])

    <div class="row g-4">
        {{-- Order Summary (rail on the right for desktop, on top for mobile) --}}
        <div class="col-lg-4 order-lg-2">
            <div class="aroma-card checkout-summary sticky-top">
                <div class="card-body">
                    <h5 class="card-title">{{ __('checkout.review') }}</h5>

                    <div class="aroma-promo-box mb-3 pb-3 border-bottom">
                        @if($promo)
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="aroma-badge-status aroma-badge-success"><i class="bi bi-check-circle-fill me-1"></i>{{ __('promo.applied_label') }}</span>
                                    <div class="fw-semibold mt-1" dir="ltr">{{ $promo['code'] }}</div>
                                </div>
                                <form method="post" action="{{ route('checkout.promo.remove') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-link text-danger btn-sm p-0">{{ __('promo.remove') }}</button>
                                </form>
                            </div>
                        @else
                            <form method="post" action="{{ route('checkout.promo.apply') }}" class="d-flex gap-2 align-items-start">
                                @csrf
                                <div class="flex-grow-1">
                                    <input type="text" name="code" value="{{ old('code') }}"
                                           class="form-control @error('code') is-invalid @enderror aroma-promo-input"
                                           placeholder="{{ __('promo.placeholder') }}" dir="ltr" autocomplete="off">
                                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-aroma-outline flex-shrink-0">{{ __('promo.apply') }}</button>
                            </form>
                        @endif
                    </div>

                    {{-- Items List --}}
                    <div class="mb-3 pb-3 border-bottom">
                        @foreach(session('cart', []) as $item)
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2 small">
                                <div>
                                    <strong>{{ $item['name'][app()->getLocale()] ?? $item['name']['en'] }}</strong>
                                    <div class="text-aroma-muted small">x{{ $item['qty'] }}</div>
                                </div>
                                <div class="text-end">@price($item['unit_price'] * $item['qty'])</div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Totals --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-aroma-muted">{{ __('checkout.subtotal') }}</span>
                            <span>@price($totals['subtotal'])</span>
                        </div>

                        @if($totals['discount_amount'] > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.discount') }}</span>
                                <span class="text-success">-@price($totals['discount_amount'])</span>
                            </div>
                        @endif

                        @if($totals['shipping_cost'] > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.shipping') }}</span>
                                <span>@price($totals['shipping_cost'])</span>
                            </div>
                        @endif

                        @if($totals['tax_amount'] > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.tax') }}</span>
                                <span>@price($totals['tax_amount'])</span>
                            </div>
                        @endif

                        @if(($totals['gift_wrap_fee'] ?? 0) > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('gift.wrap_title') }}</span>
                                <span>@price($totals['gift_wrap_fee'])</span>
                            </div>
                        @endif

                        @if(($totals['greeting_card_fee'] ?? 0) > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('gift.card_fee_title') }}</span>
                                <span>@price($totals['greeting_card_fee'])</span>
                            </div>
                        @endif
                    </div>

                    <div class="checkout-summary-total">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-aroma-brown">{{ __('checkout.total') }}</strong>
                            <strong class="text-aroma-brown" style="font-size:1.25rem">@price($totals['total_amount'])</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment Form --}}
        <div class="col-lg-8 order-lg-1">
            <h2 class="aroma-section-title">{{ __('checkout.payment') }}</h2>

            <form method="POST" action="{{ route('checkout.payment.store') }}" id="paymentForm" class="needs-validation">
                @csrf

                {{-- Payment method --}}
                {{-- Card-type methods (mada, visa, …) settle through Moyasar; BNPL
                     options are their own gateways. PaymentRequest's `in:` rule only
                     accepts moyasar|tabby|tamara, so each method maps to its gateway.
                     NB: inline @php only in this file (a raw php block breaks Blade here). --}}
                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('checkout.payment_method') }}</h5>

                        {{-- A method is selectable only when its gateway is configured.
                             Tabby/Tamara therefore show as "coming soon" until their keys
                             are added — no code change needed to switch them on. --}}
                        @php($gatewayReady = [
                            'moyasar' => (bool) config('services.moyasar.secret_key'),
                            'tabby'   => (bool) (config('services.tabby.secret_key') && config('services.tabby.merchant_code')),
                            'tamara'  => (bool) config('services.tamara.api_token'),
                        ])
                        @php($needsDefault = true)
                        <div class="aroma-pay-methods">
                            @foreach(config('aroma.payments.methods', []) as $method)
                                @php($isBnpl = in_array($method, config('aroma.payments.bnpl', []), true))
                                @php($gateway = $isBnpl ? $method : 'moyasar')
                                @php($enabled = $gatewayReady[$gateway] ?? false)
                                @php($checked = $enabled && $needsDefault)
                                @php($needsDefault = $needsDefault && ! $enabled)
                                <input type="radio" class="btn-check" name="gateway" id="gateway-{{ $method }}"
                                       value="{{ $gateway }}" data-method="{{ $method }}"
                                       {{ $enabled ? 'required' : 'disabled' }}
                                       {{ $checked ? 'checked' : '' }}>
                                <label class="aroma-pay-method {{ $enabled ? '' : 'is-disabled' }}" for="gateway-{{ $method }}">
                                    <x-payment-icon :method="$method" />
                                    <span class="aroma-pay-method-label">{{ __('checkout.payment_methods.'.strtolower($method)) }}</span>
                                    @if($enabled)
                                        <i class="bi bi-check-circle-fill aroma-pay-check"></i>
                                    @else
                                        <span class="aroma-pay-soon">{{ __('checkout.coming_soon') }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>

                        {{-- Actual card entry happens on the gateway's secure page after
                             "Place Order"; keep the concrete method for the order record. --}}
                        <input type="hidden" name="method" id="paymentMethod" value="mada">

                        @error('gateway')
                            <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                        @enderror

                        <p class="aroma-pay-secure mb-0">
                            <i class="bi bi-shield-lock"></i>{{ __('checkout.secure_checkout') }}
                        </p>
                    </div>
                </div>

                {{-- Shipping Method --}}
                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('checkout.shipping_method') }}</h5>

                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shipping_method" id="shipping-standard"
                                       value="standard" {{ old('shipping_method', 'standard') === 'standard' ? 'checked' : '' }}>
                                <label class="form-check-label" for="shipping-standard">
                                    <strong>{{ __('checkout.shipping_methods.standard') }}</strong>
                                    <div class="small text-aroma-muted">{{ __('checkout.shipping_days.standard') }}</div>
                                </label>
                            </div>
                        </div>

                        @error('shipping_method')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Receipt email — required so every order (including an
                     authenticated phone-only account with no email on file)
                     has a real address to send the purchase receipt to. --}}
                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('checkout.email') }}</h5>
                        <p class="text-aroma-muted small mb-3">{{ __('checkout.email_receipt_hint') }}</p>

                        <input type="email" name="email" id="paymentEmail"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $emailPrefill) }}" required>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Terms & Conditions — opens in a modal (matching #signatureModal/
                     #suggestionsModal in gift-options.blade.php's own multi-step
                     form) instead of navigating away, so the shopper never leaves
                     /checkout/payment and loses the gateway/shipping selections
                     above or has to re-click through the wizard to get back. --}}
                <div class="form-check mb-4">
                    <input class="form-check-input @error('terms_accepted') is-invalid @enderror" type="checkbox"
                           name="terms_accepted" id="terms" value="1" required
                           {{ old('terms_accepted') ? 'checked' : '' }}>
                    <label class="form-check-label" for="terms">
                        {{ __('checkout.agree_terms_prefix') }}
                        <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#termsModal">{{ __('checkout.agree_terms_link') }}</button>
                    </label>
                    @error('terms_accepted')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                {{-- Same content as pages/terms.blade.php, via the shared partial —
                     one source of legal text, two presentations. --}}
                <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ __('checkout.terms_modal_title') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @include('pages.partials.terms-content')
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-aroma" data-bs-dismiss="modal">{{ __('checkout.terms_modal_close') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="d-flex gap-3 justify-content-between">
                    {{-- Payment is step 6 — its predecessor is order-review (step 5),
                         not address (step 3). Was pointing at address, silently
                         skipping gift-options/order-review. --}}
                    <x-back-link :href="route('checkout.order-review')" />
                    <button type="submit" class="btn btn-aroma btn-lg">
                        {{ __('checkout.buttons.place_order') }} <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Keep the hidden `method` field in sync with the chosen option so the
        // order records the real method (mada, tabby, …), not a fixed default.
        var methodField = document.getElementById('paymentMethod');
        // Initialise from the pre-selected method.
        var preChecked = document.querySelector('input[name="gateway"]:checked');
        if (methodField && preChecked && preChecked.dataset.method) {
            methodField.value = preChecked.dataset.method;
        }
        document.querySelectorAll('input[name="gateway"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (methodField && this.dataset.method) {
                    methodField.value = this.dataset.method;
                }
            });
        });

        // TODO: Integrate the Moyasar payment SDK here (tokenize before submit).
    })();
</script>
@endpush
@endsection
