@extends('admin.layouts.master')

@section('title', __('admin.products.trashed_title').' — '.__('admin.app_name'))
@section('page_title', __('admin.products.trashed_title'))

@section('content')
    <x-admin.page-header :title="__('admin.products.trashed_title')" :subtitle="__('admin.products.trashed_subtitle')">
        <x-slot name="actions">
            <a href="{{ route('admin.products.index') }}" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-left"></i>{{ __('admin.products.title') }}</a>
        </x-slot>
    </x-admin.page-header>

    @php
        $filterChips = [];
        if (! empty($filters['q'])) { $filterChips['q'] = __('admin.common.search_label').': '.$filters['q']; }
    @endphp

    <x-admin.filter-bar :chips="$filterChips">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="admin-input" placeholder="{{ __('admin.common.search') }}">
    </x-admin.filter-bar>

    <x-admin.card :padding="false">
        @if ($products->isEmpty())
            <x-admin.empty :message="__('admin.products.no_trashed_products')" icon="bi-trash" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.products.name') }}</th>
                            <th>{{ __('admin.products.category') }}</th>
                            <th>{{ __('admin.products.archived_on') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td>
                                    <div class="admin-cell-main">{{ $product->name }}</div>
                                    <div class="admin-cell-sub">{{ $product->sku ?? '—' }}</div>
                                </td>
                                <td>{{ optional($product->category)->name ?? '—' }}</td>
                                <td>{{ optional($product->deleted_at)->format('Y-m-d H:i') }}</td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <form method="post" action="{{ route('admin.products.restore', $product) }}">
                                            @csrf
                                            <button type="submit" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.products.restore') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        </form>
                                        <form method="post" action="{{ route('admin.products.force-destroy', $product) }}" data-confirm="{{ __('admin.products.confirm_force_delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.products.force_delete') }}"><i class="bi bi-trash3"></i></button>
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
