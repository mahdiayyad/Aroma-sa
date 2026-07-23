@extends('layouts.app')

@section('title', $product->name.' — '.$brand['name'])
@section('meta_description', $product->translate('meta_description') ?? $product->translate('short_description'))

@section('content')
@php($locale = app()->getLocale())
<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home', $locale) }}">{{ __('storefront.nav.home') }}</a></li>
            @if ($product->category)
                <li class="breadcrumb-item">
                    <a href="{{ route('category.show', [$locale, $product->category->slug]) }}">{{ $product->category->name }}</a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        {{-- Gallery --}}
        <div class="col-lg-6">
            <div class="aroma-product-card p-2">
                <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}"
                     class="w-100 rounded" style="aspect-ratio:1/1;object-fit:cover;background:var(--aroma-skin)">
            </div>
            @if ($product->images->count() > 1)
                <div class="d-flex gap-2 mt-2">
                    @foreach ($product->images as $img)
                        <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}"
                             class="rounded border" style="width:72px;height:72px;object-fit:cover">
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="col-lg-6">
            @if ($product->brand)
                <div class="text-muted text-uppercase small">{{ $product->brand->name }}</div>
            @endif
            <h1 class="mb-2">{{ $product->name }}</h1>

            <div class="d-flex align-items-baseline gap-2 mb-3">
                <span class="fs-3 fw-bold" style="color:var(--aroma-brown)">
                    @if ($product->has_variants){{ __('storefront.product.from') }} @endif{{ $product->priceLabel() }}
                </span>
                @if ($product->compareAtLabel())
                    <span class="text-muted text-decoration-line-through">{{ $product->compareAtLabel() }}</span>
                    <span class="badge text-white" style="background:var(--aroma-light-brown)">{{ __('storefront.product.sale') }}</span>
                @endif
            </div>

            <p class="text-muted">{{ $product->translate('short_description') }}</p>

            {{-- Add to cart (with variant + qty) --}}
            <form method="post" action="{{ route('cart.store') }}" class="mb-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($product->has_variants && $product->variants->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('storefront.product.size') }}</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($product->variants as $i => $variant)
                                <input type="radio" class="btn-check" name="variant_id" id="variant-{{ $variant->id }}"
                                       value="{{ $variant->id }}" @checked($i === 0) {{ $variant->inStock() ? '' : 'disabled' }} required>
                                <label class="btn btn-aroma-outline btn-sm" for="variant-{{ $variant->id }}">
                                    {{ $variant->name }} · {{ $variant->priceLabel() }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="d-flex align-items-center gap-3 my-3">
                    @if ($product->inStock())
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>{{ __('storefront.product.in_stock') }}</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger">{{ __('storefront.product.sold_out') }}</span>
                    @endif
                </div>

                <div class="d-flex gap-2">
                    <input type="number" name="qty" value="1" min="1" max="99" class="form-control" style="width:90px">
                    <button type="submit" class="btn btn-aroma btn-lg flex-grow-1 {{ $product->inStock() ? '' : 'disabled' }}">
                        <i class="bi bi-bag-plus me-1"></i>{{ __('storefront.product.add_to_cart') }}
                    </button>
                </div>
            </form>

            {{-- Wishlist --}}
            @auth
                @php($isWishlisted = in_array($product->id, $wishlistIds ?? [], true))
                <form method="post" action="{{ route('wishlist.toggle', $product->slug) }}" class="mb-3">
                    @csrf
                    <button type="submit" class="btn btn-aroma-outline">
                        <i class="bi {{ $isWishlisted ? 'bi-heart-fill text-danger' : 'bi-heart' }} me-1"></i>{{ __('storefront.nav.wishlist') }}
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-aroma-outline mb-3">
                    <i class="bi bi-heart me-1"></i>{{ __('storefront.nav.wishlist') }}
                </a>
            @endauth

            {{-- Gift options (per brand differentiator) --}}
            @if ($product->is_gift_eligible)
                <div class="aroma-trust p-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="giftOptions">
                        <label class="form-check-label fw-semibold" for="giftOptions">
                            <i class="bi bi-gift me-1" style="color:var(--aroma-brown)"></i>{{ __('storefront.product.gift_options') }}
                        </label>
                    </div>
                    <small class="text-muted">{{ __('storefront.product.gift_hint') }}</small>
                </div>
            @endif

            {{-- Description accordion --}}
            <div class="accordion" id="pdpAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#descPanel">
                            {{ __('storefront.product.description') }}
                        </button>
                    </h2>
                    <div id="descPanel" class="accordion-collapse collapse show" data-bs-parent="#pdpAccordion">
                        <div class="accordion-body text-muted">
                            {{ $product->translate('description') }}
                            @if ($product->sku)
                                <div class="small mt-2">{{ __('storefront.product.sku') }}: {{ $product->sku }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
