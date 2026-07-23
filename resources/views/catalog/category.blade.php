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
    <p class="text-muted">{{ $category->translate('description') }}</p>

    <div class="row g-4 mt-1">
        {{-- Filters sidebar --}}
        <aside class="col-lg-3">
            <form method="get" class="aroma-trust p-3">
                <h6 class="fw-bold mb-3">{{ __('storefront.catalog.filters') }}</h6>

                {{-- Brands --}}
                <div class="mb-3">
                    <label class="form-label small text-uppercase">{{ __('storefront.catalog.brands') }}</label>
                    <select name="brand" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->slug }}" @selected(($filters['brand'] ?? '') === $b->slug)>{{ $b->name }}</option>
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
                        <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>{{ __('storefront.catalog.sort_newest') }}</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('storefront.catalog.sort_price_asc') }}</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('storefront.catalog.sort_price_desc') }}</option>
                    </select>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-aroma btn-sm" type="submit">{{ __('storefront.catalog.apply') }}</button>
                    <a class="btn btn-aroma-outline btn-sm" href="{{ route('category.show', [$locale, $category->slug]) }}">{{ __('storefront.catalog.clear') }}</a>
                </div>
            </form>
        </aside>

        {{-- Product grid --}}
        <div class="col-lg-9">
            <p class="text-muted small">{{ __('storefront.catalog.results', ['count' => $products->total()]) }}</p>

            @if ($products->isEmpty())
                <div class="aroma-trust p-5 text-center text-muted">{{ __('storefront.catalog.empty') }}</div>
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
@endsection
