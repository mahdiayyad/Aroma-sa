@php($locale = app()->getLocale())
@php($isWishlisted = in_array($product->id, $wishlistIds ?? [], true))
<div class="aroma-product-card aroma-card h-100">
    <div class="aroma-product-media">
        @if ($product->isOnSale())
            <span class="aroma-badge-discount">-{{ $product->discountPercent() }}%</span>
        @endif

        {{-- Wishlist toggle --}}
        @auth
            <form method="post" action="{{ route('wishlist.toggle', $product->slug) }}">
                @csrf
                <button type="submit" class="aroma-wishlist-btn {{ $isWishlisted ? 'is-active' : '' }}"
                        title="{{ __('storefront.nav.wishlist') }}" aria-label="{{ __('storefront.nav.wishlist') }}">
                    <i class="bi {{ $isWishlisted ? 'bi-heart-fill' : 'bi-heart' }}"></i>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="aroma-wishlist-btn"
               title="{{ __('storefront.nav.wishlist') }}" aria-label="{{ __('storefront.nav.wishlist') }}">
                <i class="bi bi-heart"></i>
            </a>
        @endauth

        <a href="{{ route('product.show', [$locale, $product->slug]) }}" class="d-block h-100">
            <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}" loading="lazy">
        </a>

        {{-- Quick add — slides up on hover (desktop), always visible on touch --}}
        <div class="aroma-quick-add">
            @if ($product->has_variants)
                <a href="{{ route('product.show', [$locale, $product->slug]) }}" class="btn btn-aroma btn-sm w-100">
                    <i class="bi bi-eye me-1"></i>{{ __('storefront.product.add_to_cart') }}
                </a>
            @else
                <form method="post" action="{{ route('cart.store') }}">
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

    <div class="aroma-product-body">
        @if ($product->brand)
            <span class="aroma-product-brand">{{ $product->brand->name }}</span>
        @endif
        <a href="{{ route('product.show', [$locale, $product->slug]) }}" class="aroma-product-title text-decoration-none">
            {{ $product->name }}
        </a>
        <div class="mt-auto d-flex align-items-baseline gap-2">
            <span class="aroma-price">
                @if ($product->has_variants){{ __('storefront.product.from') }} @endif{{ $product->priceLabel() }}
            </span>
            @if ($product->compareAtLabel())
                <span class="aroma-price-compare">{{ $product->compareAtLabel() }}</span>
            @endif
        </div>
    </div>
</div>
