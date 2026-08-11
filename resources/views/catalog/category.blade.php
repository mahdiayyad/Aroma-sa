@extends('layouts.app')

@section('title', $category->name.' — '.$brand['name'])
@section('meta_description', $category->translate('meta_description') ?? $category->translate('description'))

@section('content')
@php($locale = app()->getLocale())
<div class="container mt-4">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home', $locale) }}">{{ __('storefront.nav.home') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
        </ol>
    </nav>

    <h1 class="aroma-section-title">{{ $category->name }}</h1>
    <p class="text-aroma-muted">{{ $category->translate('description') }}</p>

    <div class="row g-4 mt-1">
        {{-- Mobile filter trigger --}}
        <div class="col-12 d-lg-none">
            <button class="btn btn-aroma-outline w-100" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#categoryFilters" aria-controls="categoryFilters">
                <i class="bi bi-sliders me-2"></i>{{ __('storefront.catalog.filters') }}
            </button>
        </div>

        {{-- Filters sidebar (static on desktop, slide-out drawer below lg) --}}
        <aside class="col-lg-3 offcanvas-lg offcanvas-start" tabindex="-1" id="categoryFilters" aria-labelledby="categoryFiltersLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="categoryFiltersLabel">{{ __('storefront.catalog.filters') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#categoryFilters" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form method="get" class="aroma-trust p-3">
                    {{-- Brands --}}
                    <div class="mb-3">
                        <label class="form-label small text-uppercase">{{ __('storefront.catalog.brands') }}</label>
                        <select name="brand" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach ($brands as $b)
                                <option value="{{ $b->slug }}" {{ ($filters['brand'] ?? '') === $b->slug ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Price range --}}
                    <div class="mb-3">
                        <label class="form-label small text-uppercase">{{ __('storefront.catalog.price') }}</label>
                        <div class="d-flex gap-2">
                            <input type="number" min="0" name="price_min" class="form-control form-control-sm"
                                   placeholder="{{ __('storefront.catalog.min') }}" value="{{ $filters['price_min'] ?? '' }}">
                            <input type="number" min="0" name="price_max" class="form-control form-control-sm"
                                   placeholder="{{ __('storefront.catalog.max') }}" value="{{ $filters['price_max'] ?? '' }}">
                        </div>
                    </div>

                    {{-- Sort --}}
                    <div class="mb-3">
                        <label class="form-label small text-uppercase">{{ __('storefront.catalog.sort') }}</label>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="newest" {{ ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_newest') }}</option>
                            <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_price_asc') }}</option>
                            <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_price_desc') }}</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-aroma btn-sm" type="submit">{{ __('storefront.catalog.apply') }}</button>
                        <a class="btn btn-aroma-outline btn-sm" href="{{ route('category.show', [$locale, $category->slug]) }}">{{ __('storefront.catalog.clear') }}</a>
                    </div>
                </form>
            </div>
        </aside>

        {{-- Product grid --}}
        <div class="col-lg-9">
            <p class="text-aroma-muted small">{{ __('storefront.catalog.results', ['count' => $products->total()]) }}</p>

            @if ($products->isEmpty())
                <div class="aroma-trust p-5 text-center text-aroma-muted">{{ __('storefront.catalog.empty') }}</div>
            @else
                <div class="row g-4">
                    @foreach ($products as $product)
                        <div class="col-6 col-md-4">
                            @include('catalog.partials.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>

@push('structured_data')
<script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.nav.home'), 'item' => route('home', $locale)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $category->name],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'itemListElement' => collect($products->items())->values()->map(function ($product, $i) use ($locale) {
            return [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => route('product.show', [$locale, $product->slug]),
                'name' => $product->name,
            ];
        })->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush
@endsection
