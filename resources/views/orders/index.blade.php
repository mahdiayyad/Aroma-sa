@extends('layouts.app')

@section('title', __('account.orders').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
<div class="container my-4">
    <h1 class="aroma-section-title mb-4">{{ __('account.orders') }}</h1>

    <div class="row g-4">
        <div class="col-lg-3">
            @include('account.partials.sidebar', ['active' => 'orders'])
        </div>

        <div class="col-lg-9">
            @if($orders->count() > 0)
                <div class="aroma-table-responsive">
                    <table class="aroma-table">
                        <thead>
                            <tr>
                                <th>{{ __('checkout.order_number') }}</th>
                                <th>{{ __('checkout.order_date') }}</th>
                                <th>{{ __('checkout.status') }}</th>
                                <th class="text-end">{{ __('checkout.total') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                <tr>
                                    <td><strong>{{ $order->order_number }}</strong></td>
                                    <td>{{ $order->created_at->translatedFormat('j M Y') }}</td>
                                    <td>
                                        <span class="aroma-badge-status {{ $order->statusBadgeClass() }}">
                                            {{ __('orders.status.'.$order->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end"><strong>@price($order->total_amount)</strong></td>
                                    <td class="text-end">
                                        <a href="{{ route('order.show', $order) }}" class="btn btn-aroma-outline btn-sm">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-4">
                    {{ $orders->links() }}
                </div>
            @else
                <div class="aroma-trust text-center py-5">
                    <i class="bi bi-inbox fs-1 text-aroma-light-brown"></i>
                    <h5 class="mt-3">{{ __('account.no_orders') }}</h5>
                    <p class="text-aroma-muted small mb-3">{{ __('account.no_orders_message') }}</p>
                    <a href="{{ route('home', app()->getLocale()) }}" class="btn btn-aroma btn-sm">
                        {{ __('account.start_shopping') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
