@php($locale = app()->getLocale())
@php($isWishlisted = in_array($product->id, $wishlistIds ?? [], true))
<div class="aroma-product-card h-100 position-relative">
    {{-- Wishlist toggle --}}
    @auth
        <form method="post" action="{{ route('wishlist.toggle', $product->slug) }}"
              class="position-absolute top-0 end-0 m-2" style="z-index:2">
            @csrf
            <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm" title="{{ __('storefront.nav.wishlist') }}">
                <i class="bi {{ $isWishlisted ? 'bi-heart-fill text-danger' : 'bi-heart' }}"></i>
            </button>
        </form>
    @else
        <a href="{{ route('login') }}" class="btn btn-sm btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2"
           style="z-index:2" title="{{ __('storefront.nav.wishlist') }}"><i class="bi bi-heart"></i></a>
    @endauth

    <a href="{{ route('product.show', [$locale, $product->slug]) }}" class="d-block position-relative">
        @if ($product->isOnSale())
            <span class="badge position-absolute m-2 text-white" style="background:var(--aroma-light-brown)">
                {{ __('storefront.product.sale') }}
            </span>
        @endif
        <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}"
             class="w-100" style="aspect-ratio:1/1;object-fit:cover;background:var(--aroma-skin)">
    </a>
    <div class="p-3 d-flex flex-column">
        @if ($product->brand)
            <span class="text-muted small text-uppercase">{{ $product->brand->name }}</span>
        @endif
        <a href="{{ route('product.show', [$locale, $product->slug]) }}"
           class="fw-semibold text-decoration-none mb-2" style="color:var(--aroma-ink)">
            {{ $product->name }}
        </a>
        <div class="mt-auto d-flex align-items-baseline gap-2">
            <span class="fw-bold" style="color:var(--aroma-brown)">
                @if ($product->has_variants){{ __('storefront.product.from') }} @endif{{ $product->priceLabel() }}
            </span>
            @if ($product->compareAtLabel())
                <span class="text-muted text-decoration-line-through small">{{ $product->compareAtLabel() }}</span>
            @endif
        </div>

        @if ($product->has_variants)
            {{-- Variant products pick a size on the detail page --}}
            <a href="{{ route('product.show', [$locale, $product->slug]) }}" class="btn btn-aroma btn-sm mt-3">
                <i class="bi bi-eye me-1"></i>{{ __('storefront.product.add_to_cart') }}
            </a>
        @else
            <form method="post" action="{{ route('cart.store') }}" class="mt-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <button type="submit" class="btn btn-aroma btn-sm w-100 {{ $product->inStock() ? '' : 'disabled' }}">
                    <i class="bi bi-bag-plus me-1"></i>
                    {{ $product->inStock() ? __('storefront.product.add_to_cart') : __('storefront.product.sold_out') }}
                </button>
            </form>
        @endif
    </div>
</div>
