@extends('layouts.app')

@section('title', __('checkout.review').' — '.$brand['name'])

@section('content')
@php($locale = app()->getLocale())
<div class="container checkout-page my-4 my-lg-5">

    @if(empty($items))
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="aroma-trust p-5 text-center">
                    <i class="bi bi-bag-x fs-1 d-block mb-3" style="color:var(--aroma-light-brown)"></i>
                    <h4 class="mb-2">{{ __('checkout.errors.cart_empty') }}</h4>
                    <p class="text-aroma-muted mb-4">{{ __('cart.empty') }}</p>
                    <a href="{{ route('home', $locale) }}" class="btn btn-aroma">
                        <i class="bi bi-shop me-2"></i>{{ __('shop.continue_shopping') }}
                    </a>
                </div>
            </div>
        </div>
    @else
        @include('checkout.partials.stepper', ['step' => 1])

        <div class="row g-4">
            <div class="col-lg-8">
                <h2 class="aroma-section-title">{{ __('checkout.review_order') }}</h2>

                <div class="aroma-card mb-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="small text-aroma-muted">
                                        <th class="ps-4">{{ __('checkout.product') }}</th>
                                        <th class="text-center">{{ __('checkout.quantity') }}</th>
                                        <th class="text-end">{{ __('checkout.price') }}</th>
                                        <th class="text-end pe-4">{{ __('checkout.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="{{ $item['image'] ?? '' }}" alt="" width="56" height="56"
                                                         class="rounded" style="object-fit:cover;background:var(--aroma-skin)">
                                                    <div>
                                                        <div class="fw-semibold" style="color:var(--aroma-ink)">
                                                            {{ $item['name'][$locale] ?? reset($item['name']) }}
                                                        </div>
                                                        @if(!empty($item['variant']))
                                                            <div class="small text-aroma-muted">{{ $item['variant'][$locale] ?? reset($item['variant']) }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge rounded-pill" style="background:var(--aroma-skin);color:var(--aroma-brown)">
                                                    x{{ $item['qty'] }}
                                                </span>
                                            </td>
                                            <td class="text-end">@price($item['unit_price'])</td>
                                            <td class="text-end pe-4 fw-semibold">@price($item['unit_price'] * $item['qty'])</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <a href="{{ route('cart.index') }}" class="btn btn-aroma-outline">
                    <i class="bi {{ $locale === 'ar' ? 'bi-chevron-right' : 'bi-chevron-left' }} me-2"></i>{{ __('checkout.back_to_cart') }}
                </a>
            </div>

            {{-- Order Summary --}}
            <div class="col-lg-4">
                <div class="aroma-card checkout-summary sticky-top">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('checkout.order_summary') }}</h5>

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
                        </div>

                        <div class="checkout-summary-total mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-aroma-brown">{{ __('checkout.total') }}</strong>
                                <strong class="text-aroma-brown" style="font-size:1.25rem">@price($totals['total_amount'])</strong>
                            </div>
                        </div>

                        <a href="{{ route('checkout.start') }}" class="btn btn-aroma w-100">
                            {{ __('checkout.proceed_to_checkout') }}
                            <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
                        </a>

                        <p class="small text-aroma-muted text-center mt-3 mb-0">
                            <i class="bi bi-shield-check me-1"></i>{{ __('checkout.secure_checkout') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
