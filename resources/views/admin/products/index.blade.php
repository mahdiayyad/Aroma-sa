@extends('admin.layouts.master')

@section('title', __('admin.products.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.products.title'))

@section('content')
    <x-admin.page-header :title="__('admin.products.title')" :subtitle="__('admin.products.subtitle')">
        <x-slot name="actions">
            @can('products.export')
                <div class="dropdown">
                    <button class="admin-btn admin-btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-file-earmark-excel"></i>{{ __('admin.products_io.export') }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown">
                        <li><a class="dropdown-item" href="{{ route('admin.products.export', ['scope' => 'all']) }}"><i class="bi bi-boxes me-2"></i>{{ __('admin.products_io.export_all') }}</a></li>
                        @if (\App\Support\ProductFilters::any($filters))
                            <li><a class="dropdown-item" href="{{ route('admin.products.export', array_merge($filters, ['scope' => 'filtered'])) }}"><i class="bi bi-funnel me-2"></i>{{ __('admin.products_io.export_filtered') }}</a></li>
                        @endif
                        <li>
                            <button type="submit" form="exportSelectedForm" class="dropdown-item" id="exportSelectedBtn" disabled>
                                <i class="bi bi-check2-square me-2"></i>{{ __('admin.products_io.export_selected', ['count' => 0]) }}
                            </button>
                        </li>
                    </ul>
                </div>
            @endcan
            @can('products.import')
                <a href="{{ route('admin.products.import.create') }}" class="admin-btn admin-btn-outline"><i class="bi bi-cloud-arrow-up"></i>{{ __('admin.products_io.import') }}</a>
            @endcan
            @can('products.template')
                <a href="{{ route('admin.products.template') }}" class="admin-btn admin-btn-outline"><i class="bi bi-file-earmark-arrow-down"></i>{{ __('admin.products_io.template') }}</a>
            @endcan
            <a href="{{ route('admin.products.trashed') }}" class="admin-btn admin-btn-outline"><i class="bi bi-trash"></i>{{ __('admin.products.trash') }}</a>
            <a href="{{ route('admin.products.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i>{{ __('admin.products.new') }}</a>
        </x-slot>
    </x-admin.page-header>

    @can('products.export')
        <form id="exportSelectedForm" method="post" action="{{ route('admin.products.export') }}">
            @csrf
            <input type="hidden" name="scope" value="selected">
        </form>
    @endcan

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
                            @can('products.export')
                                <th style="width: 40px">
                                    <input type="checkbox" class="form-check-input admin-check" id="selectAllProducts" aria-label="{{ __('admin.products_io.select_all') }}">
                                </th>
                            @endcan
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
                                @can('products.export')
                                    <td>
                                        <input type="checkbox" class="form-check-input admin-check js-product-select" name="ids[]" value="{{ $product->id }}"
                                               form="exportSelectedForm" aria-label="{{ __('admin.products_io.select_row') }}">
                                    </td>
                                @endcan
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
                                        <a href="{{ route('admin.products.edit', $product) }}" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('admin.common.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.products.destroy', $product) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
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

    {{ $products->links() }}
@endsection

@can('products.export')
    @push('scripts')
    <script>
        // "Export selected": enable the menu item and show the count as rows are ticked.
        (function () {
            var all = document.getElementById('selectAllProducts');
            var boxes = [].slice.call(document.querySelectorAll('.js-product-select'));
            var button = document.getElementById('exportSelectedBtn');
            var label = @json(__('admin.products_io.export_selected', ['count' => ':count']));

            function sync() {
                var n = boxes.filter(function (b) { return b.checked; }).length;
                button.disabled = n === 0;
                button.lastChild.textContent = label.replace(':count', n);
                if (all) {
                    all.checked = n > 0 && n === boxes.length;
                    all.indeterminate = n > 0 && n < boxes.length;
                }
            }

            if (all) {
                all.addEventListener('change', function () {
                    boxes.forEach(function (b) { b.checked = all.checked; });
                    sync();
                });
            }
            boxes.forEach(function (b) { b.addEventListener('change', sync); });
            sync();
        })();
    </script>
    @endpush
@endcan
