@extends('admin.layouts.master')

@section('title', $promoCode->code.' — '.__('admin.app_name'))
@section('page_title', $promoCode->code)

@section('content')
    <x-admin.page-header :title="$promoCode->code" :subtitle="$promoCode->name" :back="route('admin.promo-codes.index')">
        <x-slot name="actions">
            <a href="{{ route('admin.promo-codes.edit', $promoCode) }}" class="admin-btn admin-btn-outline"><i class="bi bi-pencil"></i>{{ __('admin.common.edit') }}</a>
        </x-slot>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-lg-4">
            <x-admin.card :title="__('admin.promo_codes.overview')">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-aroma-muted small">{{ __('admin.common.status') }}</span>
                    <x-admin.badge :tone="$promoCode->is_active ? 'success' : 'neutral'" :label="$promoCode->is_active ? __('admin.common.active') : __('admin.common.inactive')" />
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-aroma-muted small">{{ __('admin.promo_codes.discount') }}</span>
                    <span>
                        @if ($promoCode->discount_type === \App\Models\PromoCode::TYPE_PERCENTAGE)
                            {{ (int) $promoCode->discount_value }}%
                        @else
                            @price($promoCode->discount_value)
                        @endif
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-aroma-muted small">{{ __('admin.promo_codes.usage') }}</span>
                    <span>{{ $promoCode->used_count }}{{ $promoCode->usage_limit ? ' / '.$promoCode->usage_limit : '' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-aroma-muted small">{{ __('admin.promo_codes.starts_at') }}</span>
                    <span>{{ $promoCode->starts_at ? $promoCode->starts_at->translatedFormat('j M Y') : '—' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-aroma-muted small">{{ __('admin.promo_codes.expires_at') }}</span>
                    <span>{{ $promoCode->expires_at ? $promoCode->expires_at->translatedFormat('j M Y') : '—' }}</span>
                </div>
                @if ($promoCode->first_order_only)
                    <span class="admin-badge admin-badge-info">{{ __('admin.promo_codes.first_order_only') }}</span>
                @endif
                @if ($promoCode->customer_restricted)
                    <span class="admin-badge admin-badge-info">{{ __('admin.promo_codes.customer_restricted') }}</span>
                @endif
                @if ($promoCode->free_shipping)
                    <span class="admin-badge admin-badge-info">{{ __('admin.promo_codes.free_shipping') }}</span>
                @endif
            </x-admin.card>
        </div>

        <div class="col-lg-8">
            <x-admin.card :title="__('admin.promo_codes.redemptions')" :padding="false">
                @if ($promoCode->redemptions->isEmpty())
                    <x-admin.empty :message="__('admin.promo_codes.no_redemptions')" icon="bi-receipt" />
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.orders.number') }}</th>
                                    <th>{{ __('admin.customers.title') }}</th>
                                    <th class="text-end">{{ __('admin.promo_codes.discount') }}</th>
                                    <th>{{ __('admin.common.created') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($promoCode->redemptions as $redemption)
                                    <tr>
                                        <td>
                                            @if ($redemption->order)
                                                <a href="{{ route('admin.orders.show', $redemption->order) }}">{{ $redemption->order->order_number }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ optional($redemption->user)->name ?? '—' }}</td>
                                        <td class="text-end">@price($redemption->discount_amount)</td>
                                        <td>{{ $redemption->created_at->translatedFormat('j M Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
