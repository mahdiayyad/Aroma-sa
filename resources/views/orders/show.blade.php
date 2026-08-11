@extends('layouts.app')

@section('title', __('orders.title').' '.$order->order_number.' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
<div class="container my-4">
    <div class="mb-4">
        <a href="{{ route('order.index') }}" class="text-decoration-none text-aroma-brown fw-semibold">
            <i class="bi bi-chevron-left me-2"></i>{{ __('account.back_to_orders') }}
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Order Header --}}
            <div class="aroma-card mb-4">
                <div class="card-body p-4">
                    <div class="row mb-4 pb-4 border-bottom">
                        <div class="col-sm-6">
                            <div class="small text-aroma-muted mb-1">{{ __('checkout.order_number') }}</div>
                            <h5 class="text-aroma-brown">{{ $order->order_number }}</h5>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <div class="small text-aroma-muted mb-1">{{ __('checkout.order_date') }}</div>
                            <h5>{{ $order->created_at->translatedFormat('j M Y, g:i A') }}</h5>
                        </div>
                    </div>

                    {{-- Status --}}
                    <div>
                        <div class="small text-aroma-muted mb-2">{{ __('checkout.status') }}</div>
                        <span class="aroma-badge-status {{ $order->statusBadgeClass() }}">
                            {{ __('orders.status.'.$order->status) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Order Items --}}
            <div class="aroma-card mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4 text-aroma-brown">{{ __('checkout.order_items') }}</h5>

                    <div class="aroma-table-responsive">
                        <table class="aroma-table">
                            <thead>
                                <tr>
                                    <th>{{ __('cart.product') }}</th>
                                    <th class="text-end">{{ __('cart.price') }}</th>
                                    <th class="text-center">{{ __('cart.quantity') }}</th>
                                    <th class="text-end">{{ __('cart.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    <tr>
                                        <td>
                                            <div class="d-flex gap-3 align-items-start">
                                                @if($item->product_data['image'] ?? null)
                                                    <img src="{{ $item->product_data['image'] }}" alt="" class="aroma-order-item-thumb">
                                                @endif
                                                <div>
                                                    <strong>{{ $item->product_data['name'] ?? 'Product' }}</strong>
                                                    @if($item->variant_data)
                                                        <div class="small text-aroma-muted">{{ $item->variant_data['name'] ?? '' }}</div>
                                                    @endif
                                                    @if($item->product_data['sku'] ?? null)
                                                        <div class="small text-aroma-muted">{{ __('products.sku') }}: {{ $item->product_data['sku'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">{{ $item->priceLabel() }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end"><strong>{{ $item->totalLabel() }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Shipping Address --}}
            <div class="aroma-card">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3 text-aroma-brown">{{ __('checkout.shipping_address_label') }}</h5>
                    <div class="text-aroma-muted small lh-lg">
                        <div class="fw-bold text-aroma-ink">{{ $order->shipping_address['recipient_name'] }}</div>
                        <div>{{ $order->shipping_address['street_address'] }}</div>
                        <div>{{ $order->shipping_address['city'] }}, {{ $order->shipping_address['region'] }}</div>
                        @if($order->shipping_address['postal_code'])
                            <div>{{ $order->shipping_address['postal_code'] }}</div>
                        @endif
                        <div>{{ $order->customer_phone }}</div>
                    </div>

                    @if($order->tracking_number)
                        <div class="mt-3 pt-3 border-top">
                            <small class="text-aroma-muted">{{ __('orders.tracking_number') }}</small>
                            <div class="font-monospace">{{ $order->tracking_number }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Summary Sidebar --}}
        <div class="col-lg-4">
            <div class="aroma-card sticky-top" style="top:20px">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4 text-aroma-brown">{{ __('checkout.order_summary') }}</h5>

                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-aroma-muted">{{ __('checkout.subtotal') }}</span>
                            <span>@price($order->subtotal)</span>
                        </div>

                        @if($order->discount_amount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.discount') }}</span>
                                <span class="text-success">-@price($order->discount_amount)</span>
                            </div>
                        @endif

                        @if($order->shipping_cost > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.shipping') }}</span>
                                <span>@price($order->shipping_cost)</span>
                            </div>
                        @endif

                        @if($order->tax_amount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.tax') }}</span>
                                <span>@price($order->tax_amount)</span>
                            </div>
                        @endif
                    </div>

                    <div class="aroma-total-box">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-aroma-brown">{{ __('checkout.total') }}</strong>
                            <strong class="text-aroma-brown fs-5">@price($order->total_amount)</strong>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('home', app()->getLocale()) }}" class="btn btn-aroma btn-sm w-100">
                            {{ __('account.continue_shopping') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
