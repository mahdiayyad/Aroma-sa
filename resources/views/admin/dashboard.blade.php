@extends('admin.layouts.master')

@section('title', __('admin.dashboard.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.dashboard.title'))
@section('page_subtitle', __('admin.dashboard.subtitle'))

@section('content')
    <div class="admin-stats-grid">
        <x-admin.stat :label="__('admin.dashboard.revenue')" :value="\App\Support\Formatting\Money::format($revenue)" icon="bi-cash-coin" tone="success" />
        <x-admin.stat :label="__('admin.dashboard.total_orders')" :value="$ordersTotal" icon="bi-receipt" />
        <x-admin.stat :label="__('admin.dashboard.pending_orders')" :value="$ordersPending" icon="bi-hourglass-split" tone="warning" />
        <x-admin.stat :label="__('admin.dashboard.customers')" :value="$customers" icon="bi-people" tone="info" />
        <x-admin.stat :label="__('admin.dashboard.products')" :value="$productsTotal" icon="bi-box-seam" />
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card :padding="false">
                <x-slot name="head">
                    <h3 class="admin-card-title">{{ __('admin.dashboard.recent_orders') }}</h3>
                    <a href="{{ route('admin.orders.index') }}" class="admin-back-link mb-0">{{ __('admin.dashboard.view_all') }}</a>
                </x-slot>

                @if ($recentOrders->isEmpty())
                    <x-admin.empty :message="__('admin.dashboard.no_recent')" icon="bi-receipt" />
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.orders.number') }}</th>
                                    <th>{{ __('admin.orders.customer') }}</th>
                                    <th>{{ __('admin.common.status') }}</th>
                                    <th class="text-end">{{ __('admin.orders.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentOrders as $order)
                                    <tr>
                                        <td><a href="{{ route('admin.orders.show', $order) }}" class="admin-cell-main text-decoration-none">{{ $order->order_number }}</a></td>
                                        <td>{{ $order->customer_name }}</td>
                                        <td><x-admin.badge :status="$order->status" /></td>
                                        <td class="text-end">@price($order->total_amount)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card :title="__('admin.dashboard.orders_by_status')">
                @forelse ($statusCounts as $status => $count)
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <x-admin.badge :status="$status" />
                        <strong>{{ $count }}</strong>
                    </div>
                @empty
                    <p class="admin-cell-sub mb-0">{{ __('admin.dashboard.no_recent') }}</p>
                @endforelse
            </x-admin.card>

            <x-admin.card :title="__('admin.dashboard.low_stock')">
                @forelse ($lowStock as $product)
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <a href="{{ route('admin.products.edit', $product) }}" class="text-decoration-none admin-cell-main">{{ $product->name }}</a>
                        <span class="admin-badge admin-badge-{{ $product->stock_quantity > 0 ? 'warning' : 'danger' }}">{{ __('admin.dashboard.units', ['n' => $product->stock_quantity]) }}</span>
                    </div>
                @empty
                    <p class="admin-cell-sub mb-0">{{ __('admin.dashboard.all_stocked') }}</p>
                @endforelse
            </x-admin.card>
        </div>
    </div>
@endsection
