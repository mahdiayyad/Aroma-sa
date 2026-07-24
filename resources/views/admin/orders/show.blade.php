@extends('admin.layouts.master')

@section('title', $order->order_number.' — '.__('admin.app_name'))
@section('page_title', $order->order_number)

@section('content')
    <x-admin.page-header :title="$order->order_number" :subtitle="$order->created_at->translatedFormat('j M Y, g:i A')" :back="route('admin.orders.index')">
        <x-slot name="actions"><x-admin.badge :status="$order->status" /></x-slot>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card :title="__('admin.orders.items')" :padding="false">
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.orders.item') }}</th>
                                <th class="text-end">{{ __('admin.orders.unit_price') }}</th>
                                <th class="text-center">{{ __('admin.orders.qty') }}</th>
                                <th class="text-end">{{ __('admin.orders.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="admin-cell-main">{{ $item->product_data['name'] ?? '—' }}</div>
                                        @if (!empty($item->variant_data))
                                            <div class="admin-cell-sub">{{ $item->variant_data[app()->getLocale()] ?? reset($item->variant_data) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $item->priceLabel() }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">{{ $item->totalLabel() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.subtotal') }}</span><span>@price($order->subtotal)</span></div>
                    @if ($order->discount_amount > 0)<div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.discount') }}</span><span>-@price($order->discount_amount)</span></div>@endif
                    @if ($order->shipping_cost > 0)<div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.shipping') }}</span><span>@price($order->shipping_cost)</span></div>@endif
                    @if ($order->tax_amount > 0)<div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.tax') }}</span><span>@price($order->tax_amount)</span></div>@endif
                    <div class="d-flex justify-content-between pt-2 mt-2 border-top"><strong>{{ __('admin.orders.grand_total') }}</strong><strong>@price($order->total_amount)</strong></div>
                </div>
            </x-admin.card>

            <div class="row g-3">
                <div class="col-md-6">
                    <x-admin.card :title="__('admin.orders.shipping_address')">
                        @php($addr = $order->shipping_address)
                        <div class="admin-cell-sub">
                            <div>{{ $addr['recipient_name'] ?? '' }}</div>
                            <div>{{ $addr['street_address'] ?? '' }}</div>
                            <div>{{ ($addr['city'] ?? '') }}, {{ $addr['region'] ?? '' }} {{ $addr['postal_code'] ?? '' }}</div>
                            <div>{{ $order->customer_phone }}</div>
                        </div>
                    </x-admin.card>
                </div>
                <div class="col-md-6">
                    <x-admin.card :title="__('admin.orders.payment')">
                        @php($payment = $order->payment->first())
                        @if ($payment)
                            <div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.gateway') }}</span><span>{{ ucfirst($payment->gateway) }}</span></div>
                            <div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.method') }}</span><span>{{ strtoupper($payment->method) }}</span></div>
                            <div class="d-flex justify-content-between align-items-center"><span class="admin-cell-sub">{{ __('admin.common.status') }}</span><x-admin.badge :status="$payment->status" :label="ucfirst($payment->status)" /></div>
                        @else
                            <p class="admin-cell-sub mb-0">{{ __('admin.orders.no_payment') }}</p>
                        @endif
                    </x-admin.card>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <x-admin.card :title="__('admin.orders.customer_info')">
                <div class="admin-cell-main">{{ $order->customer_name }}</div>
                <div class="admin-cell-sub">{{ $order->customer_email }}</div>
                <div class="admin-cell-sub">{{ $order->customer_phone }}</div>
                @if (!$order->user_id)<span class="admin-badge admin-badge-neutral mt-2">{{ __('admin.orders.guest') }}</span>@endif
                @if ($order->customer_notes)
                    <hr class="my-2">
                    <div class="admin-cell-sub"><strong>{{ __('admin.orders.notes') }}:</strong> {{ $order->customer_notes }}</div>
                @endif
            </x-admin.card>

            <x-admin.card :title="__('admin.orders.update_status')">
                @if (empty($allowedNext))
                    <p class="admin-cell-sub mb-0">{{ __('admin.orders.no_transitions') }}</p>
                @else
                    <form method="post" action="{{ route('admin.orders.status', $order) }}">
                        @csrf @method('PATCH')
                        <x-admin.form.group :label="__('admin.orders.next_status')" name="status">
                            <select name="status" class="admin-select">
                                @foreach ($allowedNext as $status)
                                    <option value="{{ $status }}">{{ __('orders.status.'.$status) }}</option>
                                @endforeach
                            </select>
                        </x-admin.form.group>
                        <x-admin.form.group :label="__('admin.orders.tracking')" name="tracking_number">
                            <input type="text" name="tracking_number" value="{{ $order->tracking_number }}" class="admin-input">
                        </x-admin.form.group>
                        <button type="submit" class="admin-btn admin-btn-primary w-100 justify-content-center"><i class="bi bi-arrow-repeat"></i>{{ __('admin.orders.apply_status') }}</button>
                    </form>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
