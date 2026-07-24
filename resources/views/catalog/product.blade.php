@extends('layouts.app')

@section('title', $product->name.' — '.$brand['name'])
@section('meta_description', $product->translate('meta_description') ?? $product->translate('short_description'))

@section('content')
@php($locale = app()->getLocale())
<div class="container mt-4 pb-5 pb-lg-0">
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
            <div class="aroma-gallery-main">
                @if ($product->isOnSale())
                    <span class="aroma-badge-discount">-{{ $product->discountPercent() }}%</span>
                @endif
                <img id="galleryMainImg" src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}">
            </div>
            @if ($product->images->count() > 1)
                <div class="d-flex gap-2 mt-2">
                    @foreach ($product->images as $img)
                        <button type="button" class="aroma-gallery-thumb {{ $loop->first ? 'active' : '' }}"
                                data-full="{{ $img->url() }}" aria-label="{{ __('storefront.product.description') }} {{ $loop->iteration }}">
                            <img src="{{ $img->url() }}" alt="{{ $img->translate('alt') ?? $product->name }}">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="col-lg-6">
            @if ($product->brand)
                <div class="aroma-eyebrow mb-1">{{ $product->brand->name }}</div>
            @endif
            <h1 class="mb-2">{{ $product->name }}</h1>

            <div class="d-flex align-items-baseline gap-2 mb-3">
                <span class="aroma-price fs-3">
                    @if ($product->has_variants){{ __('storefront.product.from') }} @endif{{ $product->priceLabel() }}
                </span>
                @if ($product->compareAtLabel())
                    <span class="aroma-price-compare">{{ $product->compareAtLabel() }}</span>
                    <span class="aroma-badge-status aroma-badge-danger">-{{ $product->discountPercent() }}%</span>
                @endif
            </div>

            <p class="text-aroma-muted">{{ $product->translate('short_description') }}</p>

            {{-- Add to cart (with variant + qty) --}}
            <form method="post" action="{{ route('cart.store') }}" class="mb-3 js-add-to-cart" id="addToCartForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($product->has_variants && $product->variants->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('storefront.product.size') }}</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($product->variants as $i => $variant)
                                <input type="radio" class="btn-check" name="variant_id" id="variant-{{ $variant->id }}"
                                       value="{{ $variant->id }}" data-price="{{ $variant->price }}"
                                       {{ $i === 0 ? 'checked' : '' }} {{ $variant->inStock() ? '' : 'disabled' }} required>
                                <label class="btn btn-aroma-outline btn-sm" for="variant-{{ $variant->id }}">
                                    {{ $variant->name }} · {{ $variant->priceLabel() }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="d-flex align-items-center gap-3 my-3">
                    @if ($product->inStock())
                        <span class="aroma-badge-status aroma-badge-success"><i class="bi bi-check-circle"></i>{{ __('storefront.product.in_stock') }}</span>
                    @else
                        <span class="aroma-badge-status aroma-badge-danger">{{ __('storefront.product.sold_out') }}</span>
                    @endif
                </div>

                {{-- Live total: updates instantly as quantity / variant change (Item 2) --}}
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <span class="text-aroma-muted">{{ __('storefront.product.total') }}</span>
                    <span class="aroma-price fs-4 js-pdp-total"
                          data-base-price="{{ $product->base_price }}"
                          data-symbol="{{ config('aroma.currency.symbol', 'ر.س') }}"
                          data-locale="{{ app()->getLocale() }}">{{ $product->priceLabel() }}</span>
                </div>

                <div class="d-flex gap-2">
                    <input type="number" name="qty" value="1" min="1" max="99" class="form-control js-pdp-qty" style="max-width:100px">
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
                        <i class="bi {{ $isWishlisted ? 'bi-heart-fill' : 'bi-heart' }} me-1"></i>{{ __('storefront.nav.wishlist') }}
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-aroma-outline mb-3">
                    <i class="bi bi-heart me-1"></i>{{ __('storefront.nav.wishlist') }}
                </a>
            @endauth

            {{-- Description accordion --}}
            <div class="accordion" id="pdpAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#descPanel">
                            {{ __('storefront.product.description') }}
                        </button>
                    </h2>
                    <div id="descPanel" class="accordion-collapse collapse show" data-bs-parent="#pdpAccordion">
                        <div class="accordion-body text-aroma-muted">
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

{{-- Packaging & thank-you card --}}
<div class="container my-5">
    @include('catalog.partials.packaging')
</div>

{{-- Sticky mobile add-to-cart bar — submits the same #addToCartForm above --}}
<div class="aroma-mobile-cart-bar d-lg-none d-flex align-items-center gap-3">
    <div>
        <div class="small text-aroma-muted">{{ __('checkout.price') }}</div>
        <div class="aroma-price fs-5">{{ $product->priceLabel() }}</div>
    </div>
    <button type="submit" form="addToCartForm" class="btn btn-aroma flex-grow-1 {{ $product->inStock() ? '' : 'disabled' }}">
        <i class="bi bi-bag-plus me-1"></i>
        {{ $product->inStock() ? __('storefront.product.add_to_cart') : __('storefront.product.sold_out') }}
    </button>
</div>

@push('scripts')
<script>
    document.querySelectorAll('.aroma-gallery-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            document.getElementById('galleryMainImg').src = thumb.dataset.full;
            document.querySelectorAll('.aroma-gallery-thumb').forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
        });
    });

    // Live product total: price × quantity, updating with the selected variant.
    (function () {
        var totalEl = document.querySelector('.js-pdp-total');
        var qtyEl = document.querySelector('#addToCartForm .js-pdp-qty');
        if (!totalEl || !qtyEl) { return; }

        var base = parseFloat(totalEl.dataset.basePrice) || 0;
        var symbol = totalEl.dataset.symbol;
        var isAr = totalEl.dataset.locale === 'ar';

        function unitPrice() {
            var checked = document.querySelector('#addToCartForm input[name="variant_id"]:checked');
            return checked && checked.dataset.price ? parseFloat(checked.dataset.price) : base;
        }
        function money(v) {
            var s = v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return isAr ? s + ' ' + symbol : symbol + ' ' + s;
        }
        function render() {
            var qty = Math.max(1, parseInt(qtyEl.value, 10) || 1);
            totalEl.textContent = money(unitPrice() * qty);
        }

        qtyEl.addEventListener('input', render);
        document.querySelectorAll('#addToCartForm input[name="variant_id"]').forEach(function (r) {
            r.addEventListener('change', render);
        });
        render();
    })();
</script>
@endpush
@endsection
