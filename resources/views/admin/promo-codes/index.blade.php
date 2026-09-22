@extends('admin.layouts.master')

@section('title', __('admin.promo_codes.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.promo_codes.title'))

@section('content')
    <x-admin.page-header :title="__('admin.promo_codes.title')" :subtitle="__('admin.promo_codes.subtitle')">
        <x-slot name="actions">
            <a href="{{ route('admin.promo-codes.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i>{{ __('admin.promo_codes.new') }}</a>
        </x-slot>
    </x-admin.page-header>

    @php
        $filterChips = [];
        if (! empty($filters['q'])) { $filterChips['q'] = __('admin.common.search_label').': '.$filters['q']; }
        if (in_array($filters['active'] ?? '', ['0', '1'], true)) {
            $filterChips['active'] = __('admin.common.status').': '.($filters['active'] === '1' ? __('admin.common.active') : __('admin.common.inactive'));
        }
    @endphp

    <x-admin.filter-bar :chips="$filterChips">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="admin-input" placeholder="{{ __('admin.common.search') }}">
        <select name="active" class="admin-select">
            <option value="all" {{ ($filters['active'] ?? '') === 'all' ? 'selected' : '' }}>{{ __('admin.common.status') }}: {{ __('admin.common.all') }}</option>
            <option value="1" {{ ($filters['active'] ?? '') === '1' ? 'selected' : '' }}>{{ __('admin.common.active') }}</option>
            <option value="0" {{ ($filters['active'] ?? '') === '0' ? 'selected' : '' }}>{{ __('admin.common.inactive') }}</option>
        </select>
    </x-admin.filter-bar>

    <x-admin.card :padding="false">
        @if ($promoCodes->isEmpty())
            <x-admin.empty :message="__('admin.promo_codes.no_promo_codes')" icon="bi-ticket-perforated" :action="route('admin.promo-codes.create')" :actionLabel="__('admin.promo_codes.new')" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.promo_codes.code') }}</th>
                            <th>{{ __('admin.promo_codes.discount') }}</th>
                            <th class="text-center">{{ __('admin.promo_codes.usage') }}</th>
                            <th>{{ __('admin.promo_codes.expires') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($promoCodes as $promoCode)
                            <tr>
                                <td>
                                    <div class="admin-cell-main" dir="ltr">{{ $promoCode->code }}</div>
                                    <div class="admin-cell-sub">{{ $promoCode->name }}</div>
                                </td>
                                <td>
                                    @if ($promoCode->discount_type === \App\Models\PromoCode::TYPE_PERCENTAGE)
                                        {{ (int) $promoCode->discount_value }}%
                                    @else
                                        @price($promoCode->discount_value)
                                    @endif
                                    @if ($promoCode->free_shipping)
                                        <span class="admin-badge admin-badge-info ms-1">{{ __('admin.promo_codes.free_shipping') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    {{ $promoCode->used_count }}{{ $promoCode->usage_limit ? ' / '.$promoCode->usage_limit : '' }}
                                </td>
                                <td>{{ $promoCode->expires_at ? $promoCode->expires_at->translatedFormat('j M Y') : '—' }}</td>
                                <td><x-admin.badge :tone="$promoCode->is_active ? 'success' : 'neutral'" :label="$promoCode->is_active ? __('admin.common.active') : __('admin.common.inactive')" /></td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.promo-codes.show', $promoCode) }}" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.common.view') }}"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('admin.promo-codes.edit', $promoCode) }}" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.common.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.promo-codes.destroy', $promoCode) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.common.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    {{ $promoCodes->links() }}
@endsection
