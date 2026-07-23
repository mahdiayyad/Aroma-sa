@extends('layouts.app')

@section('title', $brand['name'].' — '.__('storefront.hero.title'))
@section('meta_description', __('storefront.hero.subtitle'))

@section('content')
    {{-- Hero --}}
    <section class="container mt-4">
        <div class="aroma-hero text-center aroma-animate-in">
            <div class="aroma-hero-tagline mb-2">{{ __('storefront.hero.title') }}</div>
            <h1 class="mb-3">{{ $brand['name'] }}</h1>
            <p class="lead aroma-hero-lead mb-4">{{ __('storefront.hero.subtitle') }}</p>
            <a href="#categories" class="btn btn-aroma-light btn-lg px-4">{{ __('storefront.hero.cta') }}</a>
        </div>
    </section>

    {{-- Categories --}}
    <section class="container aroma-section" id="categories">
        <h2 class="aroma-section-title">{{ __('storefront.sections.categories') }}</h2>
        <div class="row g-4">
            @foreach ($featuredCategories as $category)
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('category.show', [app()->getLocale(), $category->slug]) }}" class="text-decoration-none">
                        <div class="aroma-category-card text-center">
                            <div class="card-body py-4">
                                <i class="bi {{ $category->icon ?? 'bi-tag' }} fs-1 d-block mb-2 text-aroma-brown"></i>
                                <span class="fw-semibold">{{ $category->name }}</span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    {{-- New arrivals --}}
    @if ($newArrivals->isNotEmpty())
        <section class="container aroma-section">
            <h2 class="aroma-section-title">{{ __('storefront.sections.new_arrivals') }}</h2>
            <div class="row g-4">
                @foreach ($newArrivals as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        @include('catalog.partials.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Gifting spotlight --}}
    <section class="container aroma-section">
        <div class="row align-items-center g-4 aroma-trust p-4">
            <div class="col-md-7">
                <h2 class="aroma-section-title">{{ __('storefront.sections.gifting') }}</h2>
                <p class="fs-5 mb-2">{{ __('storefront.gifting.headline') }}</p>
                <p class="text-aroma-muted mb-3">{{ __('storefront.gifting.body') }}</p>
                <a href="#" class="btn btn-aroma">{{ __('storefront.gifting.cta') }}</a>
            </div>
            <div class="col-md-5 text-center">
                <i class="bi bi-gift aroma-icon-xl text-aroma-light-brown"></i>
            </div>
        </div>
    </section>

    {{-- Featured / bestsellers --}}
    @if ($featuredProducts->isNotEmpty())
        <section class="container aroma-section">
            <h2 class="aroma-section-title">{{ __('storefront.sections.bestsellers') }}</h2>
            <div class="row g-4">
                @foreach ($featuredProducts as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        @include('catalog.partials.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trust badges --}}
    <section class="container aroma-section">
        <h2 class="aroma-section-title">{{ __('storefront.trust.title') }}</h2>
        <div class="row g-0 aroma-trust text-center">
            <div class="col-md-4 aroma-trust-item border-end">
                <i class="bi bi-shield-check fs-2 d-block mb-2 text-aroma-brown"></i>
                <p class="mb-0 small">{{ __('storefront.trust.payments') }}</p>
            </div>
            <div class="col-md-4 aroma-trust-item border-end">
                <i class="bi bi-wallet2 fs-2 d-block mb-2 text-aroma-brown"></i>
                <p class="mb-0 small">{{ __('storefront.trust.bnpl') }}</p>
            </div>
            <div class="col-md-4 aroma-trust-item">
                <i class="bi bi-truck fs-2 d-block mb-2 text-aroma-brown"></i>
                <p class="mb-0 small">{{ __('storefront.trust.delivery') }}</p>
            </div>
        </div>
    </section>

    {{-- Newsletter --}}
    <section class="container aroma-section">
        <div class="aroma-newsletter text-center p-5">
            <h2 class="mb-2">{{ __('storefront.newsletter.title') }}</h2>
            <p class="mb-4">{{ __('storefront.newsletter.body') }}</p>
            <form class="row justify-content-center g-2" action="#" method="post">
                @csrf
                <div class="col-md-5">
                    <input type="email" class="form-control form-control-lg"
                           placeholder="{{ __('storefront.newsletter.placeholder') }}">
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-aroma-light btn-lg px-4" type="submit">
                        {{ __('storefront.newsletter.cta') }}
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
