@extends('layouts.app')

@section('title', __('checkout.order_review.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
@php($locale = app()->getLocale())
@php($isGift = $gift['is_gift'] ?? false)
<div class="container checkout-page my-4 my-lg-5">
    @include('checkout.partials.stepper', ['step' => 6])

    <h2 class="aroma-section-title">{{ __('checkout.order_review.title') }}</h2>

    <div class="row g-4">
        <div class="col-lg-8 order-lg-1">

            {{-- Items --}}
            <div class="aroma-card mb-4">
                <div class="card-body">
                    <h5 class="card-title">{{ __('checkout.order_review.items_title') }}</h5>

                    @foreach(session('cart', []) as $item)
                        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                            <img src="{{ $item['image'] }}" alt="" class="rounded" style="width:56px;height:56px;object-fit:cover;">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $item['name'][$locale] ?? $item['name']['en'] }}</div>
                                @if(!empty($item['variant']))
                                    <div class="small text-aroma-muted">{{ $item['variant'][$locale] ?? $item['variant']['en'] }}</div>
                                @endif
                                <x-option-lines :options="$item['options'] ?? []" line-class="small text-aroma-muted" />
                                <div class="small text-aroma-muted">{{ __('checkout.quantity') }}: {{ $item['qty'] }}</div>
                            </div>
                            <div class="text-end fw-semibold">@price($item['unit_price'] * $item['qty'])</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Recipient / shipping --}}
            <div class="aroma-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">{{ __('checkout.order_review.delivering_to') }}</h5>
                        <a href="{{ route($isGift ? 'checkout.gift-options' : 'checkout.address') }}" class="small">{{ __('checkout.order_review.edit') }}</a>
                    </div>
                    {{-- Shared with confirmation/orders/admin so this branching
                         logic (location-code vs pinned vs legacy address, plus
                         the demo-data badge) lives in exactly one place. --}}
                    <x-address-summary class="text-aroma-muted small" :address="$shipping" />
                </div>
            </div>

            {{-- Gift --}}
            <div class="aroma-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">
                            {{ __('gift.toggle_title') }}
                            @if($isGift && ($gift['is_anonymous'] ?? false))
                                <span class="badge bg-light text-aroma-brown border">{{ __('checkout.order_review.anonymous_badge') }}</span>
                            @endif
                        </h5>
                        <a href="{{ route('checkout.gift-options') }}" class="small">{{ __('checkout.order_review.edit') }}</a>
                    </div>

                    @if($isGift)
                        <div class="d-flex gap-3">
                            @if($giftCard)
                                <img src="{{ $giftCard->imageUrl() }}" alt="{{ $giftCard->name }}" class="rounded" style="width:64px;height:85px;object-fit:cover;flex-shrink:0;">
                            @endif
                            <div class="flex-grow-1">
                                @if(!empty($gift['to']))<div class="small text-aroma-muted">{{ __('gift.preview_to', ['name' => $gift['to']]) }}</div>@endif
                                @if(!empty($gift['message']))<p class="mb-1" style="white-space:pre-wrap;">{{ $gift['message'] }}</p>@endif
                                @if(!empty($gift['from']) && !($gift['is_anonymous'] ?? false))<div class="small text-aroma-muted">{{ __('gift.preview_from', ['name' => $gift['from']]) }}</div>@endif
                                @if(!empty($gift['signature']) && !($gift['is_anonymous'] ?? false))
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($gift['signature']) }}" alt="" style="max-height:2.5rem;" class="mt-1">
                                @endif
                                @if(!empty($gift['media_url']))
                                    <div class="small mt-1"><i class="bi bi-music-note-beamed me-1"></i><a href="{{ $gift['media_url'] }}" target="_blank" rel="noopener">{{ __('gift.media_title') }}</a></div>
                                @endif
                                @if(($gift['wrap_fee'] ?? 0) > 0)
                                    <div class="small text-aroma-muted mt-1"><i class="bi bi-check-circle me-1"></i>{{ __('gift.wrap_label') }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="text-aroma-muted mb-0 small">{{ __('checkout.order_review.not_a_gift') }}</p>
                    @endif
                </div>
            </div>

            {{-- Delivery --}}
            <div class="aroma-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">{{ __('delivery.title') }}</h5>
                        <a href="{{ route('checkout.delivery') }}" class="small">{{ __('checkout.order_review.edit') }}</a>
                    </div>
                    @if(!empty($delivery['date']))
                        <div>{{ \Illuminate\Support\Carbon::parse($delivery['date'])->translatedFormat('l, j F Y') }}</div>
                    @endif
                    @if(!empty($delivery['time_slot']))
                        <div class="text-aroma-muted small">{{ __('delivery.slots.'.$delivery['time_slot']) }} — {{ __('delivery.slot_times.'.$delivery['time_slot']) }}</div>
                    @endif
                    @if(!empty($delivery['instructions']))
                        <div class="text-aroma-muted small mt-1">{{ $delivery['instructions'] }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Totals + CTA --}}
        <div class="col-lg-4 order-lg-2">
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

                    <div class="checkout-summary-total mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-aroma-brown">{{ __('checkout.total') }}</strong>
                            <strong class="text-aroma-brown" style="font-size:1.25rem">@price($totals['total_amount'])</strong>
                        </div>
                    </div>

                    <a href="{{ route('checkout.payment') }}" class="btn btn-aroma btn-lg w-100">
                        {{ __('checkout.order_review.continue_to_payment') }}
                        <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <x-back-link :href="route('checkout.delivery')" />
    </div>
</div>
@endsection
