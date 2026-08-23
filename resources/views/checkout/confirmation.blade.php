@extends('layouts.app')

@section('title', __('checkout.confirmation').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
<div class="container checkout-page my-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            {{-- Success Message --}}
            <div class="text-center mb-5">
                <div class="mb-4">
                    <i class="bi bi-check-circle" style="font-size:4rem;color:var(--aroma-brown)"></i>
                </div>
                <h1 class="aroma-section-title mb-2">{{ __('checkout.thank_you') }}</h1>
                <p class="text-aroma-muted fs-5">{{ __('checkout.order_confirmed') }}</p>
            </div>

            {{-- Order Information --}}
            <div class="aroma-card mb-4">
                <div class="card-body p-4">
                    <div class="row mb-4 pb-4 border-bottom">
                        <div class="col-sm-6">
                            <div class="small text-aroma-muted">{{ __('checkout.order_number') }}</div>
                            <strong style="font-size:1.2rem;color:var(--aroma-brown)">{{ $order->order_number }}</strong>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <div class="small text-aroma-muted">{{ __('checkout.order_date') }}</div>
                            <strong>{{ $order->created_at->translatedFormat('j M Y, g:i A') }}</strong>
                        </div>
                    </div>

                    {{-- Order Status --}}
                    <div class="mb-4 pb-4 border-bottom">
                        <div class="small text-aroma-muted mb-2">{{ __('checkout.status') }}</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="aroma-badge-status {{ $order->statusBadgeClass() }}">
                                {{ __('orders.status.'.$order->status) }}
                            </span>
                        </div>
                    </div>

                    {{-- Shipping Address --}}
                    <div class="mb-4 pb-4 border-bottom">
                        <h6 class="fw-bold mb-3" style="color:var(--aroma-brown)">{{ __('checkout.shipping_address_label') }}</h6>
                        <x-address-summary class="text-aroma-muted small" :address="$order->shipping_address" :phone="$order->customer_phone" />
                    </div>

                    {{-- Order Items --}}
                    <div class="mb-4 pb-4 border-bottom">
                        <h6 class="fw-bold mb-3" style="color:var(--aroma-brown)">{{ __('checkout.order_items') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr class="border-0 text-aroma-muted small">
                                        <th>{{ __('cart.product') }}</th>
                                        <th class="text-end">{{ __('cart.price') }}</th>
                                        <th class="text-end">{{ __('cart.quantity') }}</th>
                                        <th class="text-end">{{ __('cart.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->items as $item)
                                        <tr class="small">
                                            <td>
                                                <strong>{{ $item->product_data['name'] ?? 'Product' }}</strong>
                                                @if(!empty($item->variant_data))
                                                    <div class="text-aroma-muted small">{{ $item->variant_data[app()->getLocale()] ?? reset($item->variant_data) }}</div>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $item->priceLabel() }}</td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ $item->totalLabel() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Order Totals --}}
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="text-aroma-muted small">{{ __('checkout.subtotal') }}</div>
                            <div>@price($order->subtotal)</div>
                        </div>
                        @if($order->discount_amount > 0)
                            <div class="col-sm-6">
                                <div class="text-aroma-muted small">{{ __('checkout.discount') }}</div>
                                <div class="text-success">-@price($order->discount_amount)</div>
                            </div>
                        @endif
                        @if($order->shipping_cost > 0)
                            <div class="col-sm-6">
                                <div class="text-aroma-muted small">{{ __('checkout.shipping') }}</div>
                                <div>@price($order->shipping_cost)</div>
                            </div>
                        @endif
                        @if($order->tax_amount > 0)
                            <div class="col-sm-6">
                                <div class="text-aroma-muted small">{{ __('checkout.tax') }}</div>
                                <div>@price($order->tax_amount)</div>
                            </div>
                        @endif

                        <div class="col-12 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong style="font-size:1.1rem">{{ __('checkout.total') }}</strong>
                                <strong style="color:var(--aroma-brown);font-size:1.3rem">@price($order->total_amount)</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Email Confirmation Message --}}
            <div class="aroma-alert aroma-alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                {{ __('checkout.email_confirmation') }}
            </div>

            {{-- Action Buttons --}}
            <div class="d-flex gap-3 justify-content-center">
                @auth
                    <a href="{{ route('order.show', $order) }}" class="btn btn-aroma-outline">
                        <i class="bi bi-eye me-2"></i>{{ __('checkout.view_order') }}
                    </a>
                @endauth
                <a href="{{ route('home', app()->getLocale()) }}" class="btn" style="background:var(--aroma-brown);color:white">
                    <i class="bi bi-shop me-2"></i>{{ __('checkout.continue_shopping') }}
                </a>
            </div>

            <p class="text-center text-aroma-muted small mt-3 mb-0">
                {{ __('checkout.returns_note') }}
                <a href="{{ route('guides.returns') }}">{{ __('checkout.returns_note_link') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection
