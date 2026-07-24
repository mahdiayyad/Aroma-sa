@extends('admin.layouts.master')

@section('title', __('admin.orders.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.orders.title'))

@section('content')
    <x-admin.page-header :title="__('admin.orders.title')" :subtitle="__('admin.orders.subtitle')" />

    <form method="get" class="admin-toolbar admin-autofilter">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="admin-input" placeholder="{{ __('admin.common.search') }}">
        <select name="status" class="admin-select">
            <option value="all">{{ __('admin.common.status') }}: {{ __('admin.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ __('orders.status.'.$status) }}</option>
            @endforeach
        </select>
        <noscript><button class="admin-btn admin-btn-outline btn-sm">{{ __('admin.common.apply') }}</button></noscript>
    </form>

    <x-admin.card :padding="false">
        @if ($orders->isEmpty())
            <x-admin.empty :message="__('admin.orders.no_orders')" icon="bi-receipt" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.orders.number') }}</th>
                            <th>{{ __('admin.orders.customer') }}</th>
                            <th>{{ __('admin.orders.date') }}</th>
                            <th class="text-center">{{ __('admin.orders.items') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-end">{{ __('admin.orders.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="admin-cell-main text-decoration-none">{{ $order->order_number }}</a></td>
                                <td>
                                    <div class="admin-cell-main">{{ $order->customer_name }}</div>
                                    <div class="admin-cell-sub">{{ $order->user_id ? '' : __('admin.orders.guest') }} {{ $order->customer_email }}</div>
                                </td>
                                <td>{{ $order->created_at->translatedFormat('j M Y') }}</td>
                                <td class="text-center">{{ $order->items_count }}</td>
                                <td><x-admin.badge :status="$order->status" /></td>
                                <td class="text-end">@price($order->total_amount)</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="admin-btn admin-btn-outline admin-btn-icon"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    {{ $orders->links() }}
@endsection
