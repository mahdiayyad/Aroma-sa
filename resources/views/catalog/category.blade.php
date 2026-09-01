@extends('layouts.app')

@section('title', $category->name.' — '.$brand['name'])
@section('meta_description', $category->translate('meta_description') ?? $category->translate('description'))

@push('head')
    <link href="{{ \App\Support\Assets::versioned('css/components/category-page.css') }}" rel="stylesheet">
@endpush

@section('content')
@php($locale = app()->getLocale())
@php($hasBrandFilter = !empty($filters['brand']))
@php($hasPriceFilter = !empty($filters['price_min']) || !empty($filters['price_max']))
@php($hasActiveFilters = $hasBrandFilter || $hasPriceFilter)
@php($activeBrand = $hasBrandFilter ? $brands->firstWhere('slug', $filters['brand']) : null)
<div class="container mt-4">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home', $locale) }}">{{ __('storefront.nav.home') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
        </ol>
    </nav>

    <div class="aroma-category-header aroma-reveal">
        <span class="aroma-eyebrow d-block mb-2">{{ __('storefront.nav.shop') }}</span>
        <h1 class="aroma-section-title">{{ $category->name }}</h1>
        @if ($category->translate('description'))
            <p class="text-aroma-muted aroma-category-description">{{ $category->translate('description') }}</p>
        @endif
    </div>

    <div class="row g-4 mt-1">
        {{-- Mobile filter trigger --}}
        <div class="col-12 d-lg-none">
            <button class="btn btn-aroma-outline w-100" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#categoryFilters" aria-controls="categoryFilters">
                <i class="bi bi-sliders me-2"></i>{{ __('storefront.catalog.filters') }}
                @if ($hasActiveFilters)
                    <span class="aroma-filter-count-badge">{{ ($hasBrandFilter ? 1 : 0) + ($hasPriceFilter ? 1 : 0) }}</span>
                @endif
            </button>
        </div>

        {{-- Filters sidebar (static on desktop, slide-out drawer below lg) --}}
        <aside class="col-lg-3 offcanvas-lg offcanvas-start" tabindex="-1" id="categoryFilters" aria-labelledby="categoryFiltersLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="categoryFiltersLabel">{{ __('storefront.catalog.filters') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#categoryFilters" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form method="get" class="aroma-filter-panel">
                    {{-- Brands --}}
                    <div class="aroma-filter-group">
                        <label class="aroma-filter-label">{{ __('storefront.catalog.brands') }}</label>
                        <select name="brand" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach ($brands as $b)
                                <option value="{{ $b->slug }}" {{ ($filters['brand'] ?? '') === $b->slug ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Price range --}}
                    <div class="aroma-filter-group">
                        <label class="aroma-filter-label">{{ __('storefront.catalog.price') }}</label>
                        <div class="d-flex gap-2">
                            <input type="number" min="0" name="price_min" class="form-control form-control-sm"
                                   placeholder="{{ __('storefront.catalog.min') }}" value="{{ $filters['price_min'] ?? '' }}">
                            <input type="number" min="0" name="price_max" class="form-control form-control-sm"
                                   placeholder="{{ __('storefront.catalog.max') }}" value="{{ $filters['price_max'] ?? '' }}">
                        </div>
                    </div>

                    {{-- Sort — also reachable from the desktop sort bar above the grid;
                         kept here too so the mobile drawer stays one complete panel. --}}
                    <div class="aroma-filter-group">
                        <label class="aroma-filter-label">{{ __('storefront.catalog.sort') }}</label>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="newest" {{ ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_newest') }}</option>
                            <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_price_asc') }}</option>
                            <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_price_desc') }}</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-aroma btn-sm" type="submit">{{ __('storefront.catalog.apply') }}</button>
                        @if ($hasActiveFilters || !empty($filters['sort']))
                            <a class="btn btn-aroma-outline btn-sm" href="{{ route('category.show', [$locale, $category->slug]) }}">{{ __('storefront.catalog.clear') }}</a>
                        @endif
                    </div>
                </form>
            </div>
        </aside>

        {{-- Product grid --}}
        <div class="col-lg-9">
            {{-- Desktop-only bar: result count + active-filter chips (left),
                 quick sort (right) — the sidebar/drawer above stays the single
                 source of truth for actually setting filters; this is just
                 faster access + a clear "here's what's applied" summary. All
                 of it still submits a normal GET reload, same as the sidebar
                 form — no backend/AJAX involved, so this carries zero of the
                 risk a real instant-filter rebuild would. --}}
            <div class="aroma-catalog-toolbar">
                <div class="aroma-catalog-toolbar-info">
                    <p class="text-aroma-muted small mb-0">{{ __('storefront.catalog.results', ['count' => $products->total()]) }}</p>
                    @if ($hasActiveFilters)
                        <div class="aroma-filter-chips">
                            @if ($activeBrand)
                                <a href="{{ request()->fullUrlWithoutQuery(['brand']) }}" class="aroma-filter-chip" title="{{ __('storefront.catalog.remove_filter') }}">
                                    {{ $activeBrand->name }} <i class="bi bi-x" aria-hidden="true"></i>
                                </a>
                            @endif
                            @if ($hasPriceFilter)
                                @php($min = $filters['price_min'] ?? null)
                                @php($max = $filters['price_max'] ?? null)
                                {{-- The numeric range is wrapped dir="ltr" on purpose — a
                                     bare "100 – 500" inside Arabic (RTL) text is exactly the
                                     kind of neutral-character/number run the bidi algorithm
                                     can flip to "500 – 100" on screen despite the source
                                     order being correct; isolating its direction prevents that. --}}
                                <a href="{{ request()->fullUrlWithoutQuery(['price_min', 'price_max']) }}" class="aroma-filter-chip" title="{{ __('storefront.catalog.remove_filter') }}">
                                    {{ __('storefront.catalog.price') }}:
                                    <span dir="ltr">@if ($min && $max){{ $min }}&nbsp;–&nbsp;{{ $max }}@elseif ($min){{ $min }}+@else&le;&nbsp;{{ $max }}@endif</span>
                                    <i class="bi bi-x" aria-hidden="true"></i>
                                </a>
                            @endif
                            <a href="{{ route('category.show', [$locale, $category->slug]) }}" class="aroma-filter-chip aroma-filter-chip-clear">{{ __('storefront.catalog.clear') }}</a>
                        </div>
                    @endif
                </div>

                <form method="get" class="aroma-catalog-toolbar-sort d-none d-lg-flex">
                    <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
                    <input type="hidden" name="price_min" value="{{ $filters['price_min'] ?? '' }}">
                    <input type="hidden" name="price_max" value="{{ $filters['price_max'] ?? '' }}">
                    <label for="catalogSort" class="visually-hidden">{{ __('storefront.catalog.sort') }}</label>
                    <select name="sort" id="catalogSort" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="newest" {{ ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_newest') }}</option>
                        <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_price_asc') }}</option>
                        <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>{{ __('storefront.catalog.sort_price_desc') }}</option>
                    </select>
                </form>
            </div>

            @if ($products->isEmpty())
                <div class="aroma-trust p-5 text-center text-aroma-muted aroma-reveal">{{ __('storefront.catalog.empty') }}</div>
            @else
                <div class="row g-4" data-reveal-group="plp-grid">
                    @foreach ($products as $product)
                        <div class="col-6 col-md-4 aroma-reveal">
                            @include('catalog.partials.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 aroma-catalog-pagination">{{ $products->links() }}</div>
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
