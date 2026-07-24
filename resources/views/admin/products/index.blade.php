@extends('admin.layouts.master')

@section('title', __('admin.products.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.products.title'))

@section('content')
    <x-admin.page-header :title="__('admin.products.title')" :subtitle="__('admin.products.subtitle')">
        <x-slot name="actions">
            <a href="{{ route('admin.products.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i>{{ __('admin.products.new') }}</a>
        </x-slot>
    </x-admin.page-header>

    <form method="get" class="admin-toolbar admin-autofilter">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="admin-input" placeholder="{{ __('admin.common.search') }}">
        <select name="category" class="admin-select">
            <option value="">{{ __('admin.products.category') }}: {{ __('admin.common.all') }}</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" {{ (string) ($filters['category'] ?? '') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="active" class="admin-select">
            <option value="all" {{ ($filters['active'] ?? '') === 'all' ? 'selected' : '' }}>{{ __('admin.common.status') }}: {{ __('admin.common.all') }}</option>
            <option value="1" {{ ($filters['active'] ?? '') === '1' ? 'selected' : '' }}>{{ __('admin.common.active') }}</option>
            <option value="0" {{ ($filters['active'] ?? '') === '0' ? 'selected' : '' }}>{{ __('admin.common.inactive') }}</option>
        </select>
        <noscript><button class="admin-btn admin-btn-outline btn-sm">{{ __('admin.common.apply') }}</button></noscript>
    </form>

    <x-admin.card :padding="false">
        @if ($products->isEmpty())
            <x-admin.empty :message="__('admin.products.no_products')" icon="bi-box-seam" :action="route('admin.products.create')" :actionLabel="__('admin.products.new')" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.products.name') }}</th>
                            <th>{{ __('admin.products.category') }}</th>
                            <th class="text-end">{{ __('admin.products.price') }}</th>
                            <th class="text-center">{{ __('admin.products.stock') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $product->primaryImageUrl() }}" alt="" class="admin-thumb">
                                        <div>
                                            <div class="admin-cell-main">{{ $product->name }}</div>
                                            <div class="admin-cell-sub">{{ $product->sku ?? '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ optional($product->category)->name ?? '—' }}</td>
                                <td class="text-end">
                                    @price($product->base_price)
                                    @if ($product->isOnSale())<span class="admin-badge admin-badge-danger ms-1">-{{ $product->discountPercent() }}%</span>@endif
                                </td>
                                <td class="text-center">
                                    <span class="admin-badge admin-badge-{{ $product->inStock() ? 'success' : 'danger' }}">{{ $product->has_variants ? '—' : $product->stock_quantity }}</span>
                                </td>
                                <td><x-admin.badge :tone="$product->is_active ? 'success' : 'neutral'" :label="$product->is_active ? __('admin.common.active') : __('admin.common.inactive')" /></td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="admin-btn admin-btn-outline admin-btn-icon" title="{{ __('admin.common.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.products.destroy', $product) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-icon" title="{{ __('admin.common.delete') }}"><i class="bi bi-trash"></i></button>
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

    {{ $products->links() }}
@endsection
