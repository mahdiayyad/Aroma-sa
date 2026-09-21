@extends('layouts.app')

@section('title', __('cart.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@push('head')
    <link href="{{ \App\Support\Assets::versioned('css/components/cart-page.css') }}" rel="stylesheet">
@endpush

@section('content')
@php($locale = app()->getLocale())
<div class="container my-4">
    <h1 class="aroma-section-title aroma-reveal">{{ __('cart.title') }}</h1>

    @if (empty($rows))
        {{-- Same decorative language as the homepage promo panel (botanical
             pattern backdrop + a real heading), not a stock icon+line+button
             empty state — reuses .aroma-pattern-bg, no new artwork. --}}
        <div class="aroma-trust aroma-pattern-bg aroma-empty-state p-5 text-center aroma-reveal">
            <div class="aroma-empty-state-icon">
                <i class="bi bi-bag" aria-hidden="true"></i>
            </div>
            <p class="aroma-heading aroma-empty-state-title">{{ __('cart.empty') }}</p>
            <a href="{{ route('home', $locale) }}" class="btn btn-aroma">{{ __('cart.empty_cta') }}</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Card rows, not a <table> — reads naturally on mobile with
                     no horizontal scroll, and every hook aroma-ui.js already
                     wires up (data-row, .js-cart-qty, .js-line-total,
                     .js-cart-remove, .js-cart-subtotal) is unchanged, so the
                     live qty/remove/subtotal behavior needs zero JS changes
                     beyond the .is-updating pulse added alongside this. --}}
                <div class="aroma-cart-items" data-reveal-group="cart-items">
                    @foreach ($rows as $rowId => $row)
                        <div class="aroma-cart-row aroma-reveal" data-row="{{ $rowId }}">
                            <a href="{{ route('product.show', [$locale, $row['slug']]) }}" class="aroma-cart-row-media">
                                <img src="{{ $row['image'] }}" alt="" loading="lazy">
                            </a>
                            <div class="aroma-cart-row-body">
                                <div class="aroma-cart-row-info">
                                    <a href="{{ route('product.show', [$locale, $row['slug']]) }}" class="aroma-cart-row-title">
                                        {{ $row['name'][$locale] ?? reset($row['name']) }}
                                    </a>
                                    @if (!empty($row['variant']))
                                        <div class="aroma-cart-row-variant">{{ $row['variant'][$locale] ?? reset($row['variant']) }}</div>
                                    @endif
                                    <x-option-lines :options="$row['options'] ?? []" line-class="aroma-cart-row-variant" />
                                    {{-- Unit price (before add-ons) — only shown here
                                         (inline with the name) below lg; the desktop
                                         layout gets its own dedicated column. --}}
                                    <div class="aroma-cart-row-unit-price d-lg-none">@price($row['base_unit_price'] ?? $row['unit_price'])</div>
                                </div>

                                <div class="aroma-cart-row-price d-none d-lg-block">@price($row['base_unit_price'] ?? $row['unit_price'])</div>

                                <div class="aroma-cart-row-qty">
                                    <div class="aroma-qty-stepper aroma-qty-sm">
                                        <button type="button" class="aroma-qty-btn aroma-qty-minus" aria-label="{{ __('cart.decrease') }}">&minus;</button>
                                        <input type="number" value="{{ $row['qty'] }}" min="0" max="99"
                                               class="form-control form-control-sm js-cart-qty aroma-qty-input"
                                               data-url="{{ route('cart.update', $rowId) }}"
                                               aria-label="{{ __('cart.qty') }}">
                                        <button type="button" class="aroma-qty-btn aroma-qty-plus" aria-label="{{ __('cart.increase') }}">+</button>
                                    </div>
                                </div>

                                <div class="aroma-cart-row-total">
                                    <span class="aroma-cart-row-total-label d-lg-none">{{ __('cart.total') }}</span>
                                    <span class="js-line-total">@price($row['unit_price'] * $row['qty'])</span>
                                </div>

                                <form method="post" action="{{ route('cart.remove', $rowId) }}" class="js-cart-remove aroma-cart-row-remove">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="aroma-cart-remove-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('cart.remove') }}" aria-label="{{ __('cart.remove') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
                <form method="post" action="{{ route('cart.clear') }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-link text-aroma-muted p-0" type="submit">{{ __('cart.clear') }}</button>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="aroma-cart-summary aroma-reveal">
                    <h5 class="aroma-cart-summary-title">{{ __('cart.summary') }}</h5>
                    <div class="aroma-cart-summary-row">
                        <span>{{ __('cart.subtotal') }}</span>
                        <span class="aroma-cart-summary-value js-cart-subtotal">{{ $subtotal }}</span>
                    </div>

                    @include('partials.tamara-widget', [
                        'amount' => collect($rows)->sum(function ($row) { return $row['unit_price'] * $row['qty']; }),
                        'live' => 'cart',
                    ])

                    <a href="{{ route('checkout.review') }}" class="btn btn-aroma w-100 mb-2">{{ __('cart.checkout') }}</a>
                    <a href="{{ route('home', $locale) }}" class="btn btn-aroma-outline w-100">{{ __('cart.continue') }}</a>

                    {{-- Honest reassurance only — reusing the exact same claims
                         already shown on the homepage perks row (real gift-wrap
                         and delivery copy, not new claims invented here), plus
                         one generic, non-quantitative secure-payment line. --}}
                    <ul class="aroma-cart-trust list-unstyled">
                        <li><i class="bi bi-gift" aria-hidden="true"></i>{{ __('storefront.trust.gift_wrap') }}</li>
                        <li><i class="bi bi-truck" aria-hidden="true"></i>{{ __('storefront.trust.delivery') }}</li>
                        <li><i class="bi bi-shield-check" aria-hidden="true"></i>{{ __('cart.trust_secure') }}</li>
                    </ul>

                    <div class="aroma-cart-payment-icons">
                        <x-payment-icon method="mada" />
                        <x-payment-icon method="visa" />
                        <x-payment-icon method="mastercard" />
                        <x-payment-icon method="applepay" />
                        <x-payment-icon method="tabby" />
                        <x-payment-icon method="tamara" />
                    </div>
                    <p class="aroma-cart-bnpl"><i class="bi bi-wallet2" aria-hidden="true"></i>{{ __('cart.bnpl') }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
