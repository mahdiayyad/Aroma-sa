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
                                        @if ($item->variantLabel())
                                            <div class="admin-cell-sub">{{ $item->variantLabel() }}</div>
                                        @endif
                                        <x-option-lines :options="$item->optionRows()" line-class="admin-cell-sub" />
                                    </td>
                                    <td class="text-end">{{ $item->baseUnitPriceLabel() }}</td>
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
                        <x-address-summary class="admin-cell-sub" :address="$order->shipping_address" :phone="$order->customer_phone" />
                    </x-admin.card>
                </div>
                <div class="col-md-6">
                    <x-admin.card :title="__('admin.orders.payment')">
                        @php($payment = $order->payment->first())
                        @if ($payment)
                            <div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.gateway') }}</span><span>{{ ucfirst($payment->gateway) }}</span></div>
                            <div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.orders.method') }}</span><span>{{ strtoupper($payment->method) }}</span></div>
                            <div class="d-flex justify-content-between align-items-center"><span class="admin-cell-sub">{{ __('admin.common.status') }}</span><x-admin.badge :status="$payment->status" :label="ucfirst($payment->status)" /></div>

                            {{-- Tamara: capture happens on shipment, refunds go back through
                                 Tamara only. --}}
                            @if ($payment->gateway === 'tamara')
                                @php($tamaraRemaining = round((float) $payment->amount - (float) $payment->refunded_amount, 2))
                                <hr class="my-3">
                                <div class="fw-semibold mb-2">{{ __('admin.tamara.panel_title') }}</div>
                                @if ($payment->reference_number)
                                    <div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">Tamara ID</span><span class="small">{{ $payment->reference_number }}</span></div>
                                @endif
                                @if ((float) $payment->refunded_amount > 0)
                                    <div class="d-flex justify-content-between mb-1"><span class="admin-cell-sub">{{ __('admin.tamara.refunded_so_far') }}</span><span>{{ number_format((float) $payment->refunded_amount, 2) }}</span></div>
                                @endif

                                @if ($payment->status === \App\Models\Payment::STATUS_AUTHORIZED && in_array($order->status, [\App\Models\Order::STATUS_SHIPPED, \App\Models\Order::STATUS_DELIVERED], true))
                                    <form method="post" action="{{ route('admin.orders.tamara.capture', $order) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-arrow-repeat"></i>{{ __('admin.tamara.capture_retry') }}</button>
                                    </form>
                                @endif

                                @if ($payment->status === \App\Models\Payment::STATUS_CAPTURED && $tamaraRemaining > 0)
                                    <form method="post" action="{{ route('admin.orders.tamara.refund', $order) }}" class="mt-3"
                                          onsubmit="return confirm(@json(__('admin.tamara.refund_confirm')));">
                                        @csrf
                                        <div class="fw-semibold small mb-1">{{ __('admin.tamara.refund_title') }}</div>
                                        <div class="mb-2">
                                            <label class="admin-cell-sub small" for="tamaraRefundAmount">{{ __('admin.tamara.refund_amount') }}</label>
                                            <input type="number" step="0.01" min="0.01" max="{{ $tamaraRemaining }}" name="amount" id="tamaraRefundAmount"
                                                   value="{{ $tamaraRemaining }}" class="admin-input" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="admin-cell-sub small" for="tamaraRefundComment">{{ __('admin.tamara.refund_comment') }}</label>
                                            <input type="text" name="comment" id="tamaraRefundComment" maxlength="250" class="admin-input" required>
                                        </div>
                                        <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-arrow-counterclockwise"></i>{{ __('admin.tamara.refund_submit') }}</button>
                                        <p class="admin-cell-sub small mt-2 mb-0">{{ __('admin.tamara.refund_note') }}</p>
                                    </form>
                                @endif
                            @endif
                        @else
                            <p class="admin-cell-sub mb-0">{{ __('admin.orders.no_payment') }}</p>
                        @endif
                    </x-admin.card>
                </div>
            </div>

            {{-- Fulfilment relies on manual reading of this card — there is no
                 carrier integration (data capture only). Delivery scheduling
                 was removed from checkout entirely (every order ships on the
                 same fixed timeline), so there is no longer a sibling card here. --}}
            <div class="row g-3">
                <div class="col-12">
                    <x-admin.card :title="__('admin.orders.gift')">
                        @if ($order->is_gift)
                            <div class="d-flex align-items-start gap-3">
                                @if ($order->giftCard)
                                    <img src="{{ $order->giftCard->imageUrl() }}" alt="" class="admin-thumb" style="width:56px;height:75px;object-fit:cover;">
                                @endif
                                <div class="flex-grow-1">
                                    @if ($order->is_anonymous)
                                        <span class="admin-badge admin-badge-neutral mb-2">{{ __('admin.orders.anonymous') }}</span>
                                    @endif
                                    @if ($order->gift_card_to)<div class="admin-cell-sub"><strong>{{ __('admin.orders.gift_to') }}:</strong> {{ $order->gift_card_to }}</div>@endif
                                    @if ($order->gift_message)<div class="admin-cell-sub my-1" style="white-space:pre-wrap;">{{ $order->gift_message }}</div>@endif
                                    @if ($order->gift_card_from && !$order->is_anonymous)<div class="admin-cell-sub"><strong>{{ __('admin.orders.gift_from') }}:</strong> {{ $order->gift_card_from }}</div>@endif
                                    @if ($order->gift_signature && !$order->is_anonymous)
                                        <div class="mt-2"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->gift_signature) }}" alt="" style="max-height:2.5rem;"></div>
                                    @endif
                                    @if ($order->gift_media_url)<div class="admin-cell-sub mt-1"><i class="bi bi-music-note-beamed me-1"></i><a href="{{ $order->gift_media_url }}" target="_blank" rel="noopener">{{ __('admin.orders.gift_media') }}</a></div>@endif
                                    @if ($order->gift_wrap_fee > 0)<div class="admin-cell-sub mt-1"><i class="bi bi-check-circle me-1"></i>{{ __('admin.orders.gift_wrap') }} (@price($order->gift_wrap_fee))</div>@endif
                                    @if ($order->greeting_card_fee > 0)<div class="admin-cell-sub mt-1"><i class="bi bi-tag me-1"></i>{{ __('admin.orders.card_fee') }} (@price($order->greeting_card_fee))</div>@endif
                                </div>
                            </div>
                        @else
                            <p class="admin-cell-sub mb-0">{{ __('admin.orders.not_a_gift') }}</p>
                        @endif
                    </x-admin.card>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <x-admin.card :title="__('admin.orders.customer_info')">
                <div class="admin-cell-main">{{ $order->customer_name }}</div>
                <div class="admin-cell-sub">{{ $order->customer_email }}</div>
                <div class="admin-cell-sub" dir="ltr">{{ $order->customer_phone }}</div>
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
