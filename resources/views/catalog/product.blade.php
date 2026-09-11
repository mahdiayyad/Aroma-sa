@extends('layouts.app')

@section('title', $product->name.' — '.$brand['name'])
@section('meta_description', $product->translate('meta_description') ?? $product->translate('short_description'))
@section('og_type', 'product')
@section('og_image', url($product->primaryImageUrl()))

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

    @php($first = $gallery->first())

    <div class="row g-4">
        {{-- Gallery --}}
        <div class="col-lg-6">
            <div class="aroma-gallery">
                <figure class="aroma-gallery-main">
                    @if ($product->isOnSale())
                        <span class="aroma-badge-discount">-{{ $product->discountPercent() }}%</span>
                    @endif
                    <img id="galleryMainImg" src="{{ $first['url'] }}" alt="{{ $first['alt'] }}">
                    <figcaption class="aroma-gallery-caption {{ $first['label'] === '' ? 'is-empty' : '' }}" id="galleryCaption">{{ $first['label'] }}</figcaption>
                    {{-- Maximize — opens the same image full-size and uncropped
                         (object-fit:contain, unlike this cover-cropped main
                         view) in the lightbox below. --}}
                    <button type="button" class="aroma-gallery-zoom-btn" id="galleryZoomBtn"
                            data-bs-toggle="modal" data-bs-target="#galleryLightbox"
                            data-bs-tooltip="true" data-bs-placement="top"
                            title="{{ __('storefront.product.zoom_image') }}" aria-label="{{ __('storefront.product.zoom_image') }}">
                        <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                    </button>
                </figure>

                @if ($gallery->count() > 1)
                    <div class="aroma-gallery-thumbs" role="list">
                        @foreach ($gallery as $g)
                            <button type="button" role="listitem"
                                    class="aroma-gallery-thumb {{ $loop->first ? 'active' : '' }}"
                                    data-full="{{ $g['url'] }}" data-label="{{ $g['label'] }}"
                                    aria-label="{{ $g['label'] !== '' ? $g['label'] : $product->name.' '.$loop->iteration }}">
                                <img src="{{ $g['url'] }}" alt="{{ $g['alt'] }}" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Lightbox — the product's own images shown full-size and uncropped,
             with the same prev/next set the thumbnail strip has when there's
             more than one. Kept outside .aroma-gallery/.col-lg-6 (a Bootstrap
             modal is positioned relative to <body>, not its markup position),
             but still inside the page — no separate view/route needed. --}}
        <div class="modal fade aroma-lightbox" id="galleryLightbox" tabindex="-1" aria-hidden="true" aria-label="{{ __('storefront.product.zoom_image') }}">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <div class="modal-content">
                    <button type="button" class="aroma-lightbox-close" data-bs-dismiss="modal" aria-label="{{ __('cart.modal.close') }}">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                    <div class="aroma-lightbox-stage">
                        @if ($gallery->count() > 1)
                            <button type="button" class="aroma-lightbox-nav aroma-lightbox-prev" data-dir="-1" aria-label="{{ __('storefront.product.prev_image') }}">
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </button>
                        @endif
                        <img id="lightboxImg" src="{{ $first['url'] }}" alt="{{ $first['alt'] }}">
                        @if ($gallery->count() > 1)
                            <button type="button" class="aroma-lightbox-nav aroma-lightbox-next" data-dir="1" aria-label="{{ __('storefront.product.next_image') }}">
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </button>
                        @endif
                    </div>
                    @if ($gallery->count() > 1)
                        <div class="aroma-lightbox-counter" id="lightboxCounter" aria-live="polite"
                             data-template="{{ __('storefront.product.image_of', ['current' => '{n}', 'total' => '{t}']) }}"></div>
                    @endif
                </div>
            </div>
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
                        <span class="aroma-eyebrow d-block mb-2">{{ __('storefront.product.size') }}</span>
                        <div class="aroma-variant-grid">
                            @foreach ($product->variants as $i => $variant)
                                <input type="radio" class="btn-check" name="variant_id" id="variant-{{ $variant->id }}"
                                       value="{{ $variant->id }}" data-price="{{ $variant->price }}"
                                       {{ $i === 0 ? 'checked' : '' }} {{ $variant->inStock() ? '' : 'disabled' }} required>
                                <label class="btn btn-aroma-outline aroma-variant-option {{ $variant->inStock() ? '' : 'aroma-variant-option-oos' }}"
                                       for="variant-{{ $variant->id }}">
                                    <span class="aroma-variant-name">{{ $variant->name }}</span>
                                    <span class="aroma-variant-price">
                                        {{ $variant->priceLabel() }}
                                        @unless ($variant->inStock())
                                            <span class="aroma-variant-oos-tag">{{ __('storefront.product.sold_out') }}</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Customisation options. The numeric size range renders as a
                     dropdown; short-list choices (closure, dress add-on) render
                     as a radio-button grid. A required option with no default
                     forces a choice (also enforced server-side). --}}
                @foreach ($product->options as $option)
                    @php($optionValues = $option->values->where('is_active', true)->values())
                    @continue($optionValues->isEmpty())
                    @php($hasDefault = $optionValues->contains('is_default', true))
                    <div class="mb-3 js-option-group">
                        @if ($option->key === 'size')
                            <label class="aroma-eyebrow d-block mb-2" for="option-{{ $option->id }}">
                                {{ $option->label }}@if ($option->is_required) <span class="aroma-required">*</span>@endif
                            </label>
                            {{-- Auto-enhanced by select2-init.js to match the Aroma
                                 select theme; the native <select> stays in the DOM
                                 (Select2 keeps its value in sync) so form submit and
                                 the live-total script below work unchanged. --}}
                            <select name="options[{{ $option->id }}]" id="option-{{ $option->id }}"
                                    class="form-select js-option-select"
                                    data-placeholder="{{ __('storefront.product.size_placeholder') }}"
                                    {{ $option->is_required ? 'required' : '' }}>
                                <option value=""></option>
                                @foreach ($optionValues as $value)
                                    <option value="{{ $value->id }}" data-price-delta="{{ $value->price_delta }}"
                                            {{ $hasDefault && $value->is_default ? 'selected' : '' }}>
                                        {{ $value->label }}@if ($value->priceDeltaLabel()) — {{ $value->priceDeltaLabel() }}@endif
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <span class="aroma-eyebrow d-block mb-2">
                                {{ $option->label }}@if ($option->is_required) <span class="aroma-required">*</span>@endif
                            </span>
                            <div class="aroma-variant-grid">
                                @foreach ($optionValues as $value)
                                    <input type="radio" class="btn-check js-option-input" name="options[{{ $option->id }}]"
                                           id="option-{{ $option->id }}-{{ $value->id }}" value="{{ $value->id }}"
                                           data-price-delta="{{ $value->price_delta }}"
                                           {{ $hasDefault && $value->is_default ? 'checked' : '' }}
                                           {{ $option->is_required ? 'required' : '' }}>
                                    <label class="btn btn-aroma-outline aroma-variant-option" for="option-{{ $option->id }}-{{ $value->id }}">
                                        <span class="aroma-variant-name">{{ $value->label }}</span>
                                        @if ($value->priceDeltaLabel())
                                            {{-- Latin-first stack: the "+" glyph has no form in the Arabic display face. --}}
                                            <span class="aroma-variant-price" style="font-family:var(--aroma-input-font)">{{ $value->priceDeltaLabel() }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

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
                    <div class="aroma-qty-stepper">
                        <button type="button" class="aroma-qty-btn aroma-qty-minus" aria-label="{{ __('storefront.product.qty_decrease') }}">&minus;</button>
                        <input type="number" name="qty" value="1" min="1" max="99" class="form-control js-pdp-qty aroma-qty-input">
                        <button type="button" class="aroma-qty-btn aroma-qty-plus" aria-label="{{ __('storefront.product.qty_increase') }}">+</button>
                    </div>
                    <button type="submit" class="btn btn-aroma btn-lg flex-grow-1 {{ $product->inStock() ? '' : 'disabled' }}">
                        <i class="bi bi-bag-plus me-1"></i>{{ __('storefront.product.add_to_cart') }}
                    </button>
                </div>
            </form>

            {{-- Wishlist --}}
            @auth
                @php($isWishlisted = in_array($product->id, $wishlistIds ?? [], true))
                <form method="post" action="{{ route('wishlist.toggle', $product->slug) }}" class="mb-3 js-wishlist">
                    @csrf
                    <button type="submit" class="btn btn-aroma-outline {{ $isWishlisted ? 'is-active' : '' }}">
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

            {{-- طرق الدفع --}}
            @include('catalog.partials.payment-methods')

            {{-- The abaya category slug differs by environment (abaya /
                 abayas) — matches ProductOptionSeeder's own check. Moot while
                 the category is deactivated (PerfumeCatalogSeeder), but keeps
                 this correct on whichever environment abayas come back on. --}}
            @if ($product->category && in_array($product->category->slug, ['abaya', 'abayas'], true))
                @include('catalog.partials.guide-links')
            @endif
        </div>
    </div>
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

@push('structured_data')
<script type="application/ld+json">
    {!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
    {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@push('scripts')
<script>
    (function () {
        var main = document.getElementById('galleryMainImg');
        var caption = document.getElementById('galleryCaption');
        var thumbs = document.querySelectorAll('.aroma-gallery-thumb');
        if (!main || !thumbs.length) { return; }

        var thumbList = Array.prototype.slice.call(thumbs);
        var isRtl = document.documentElement.getAttribute('dir') === 'rtl';
        var SLIDE_MS = 320; // keep in sync with .aroma-gallery-main img's transition duration in cards.css

        function select(thumb) {
            var full = thumb.dataset.full;

            // Ignored, not queued, while a slide is already mid-flight — a
            // second click landing inside that ~320ms window would otherwise
            // desync the active-thumbnail highlight/caption (updated below)
            // from whichever image actually ends up on screen.
            if (main.dataset.sliding) { return; }
            if (main.getAttribute('src') === full) { return; }

            var newAlt = thumb.querySelector('img').alt;

            // Direction follows the thumbnail's position relative to the one
            // currently showing — later in the strip slides in from the
            // right (current image exits left), earlier slides in from the
            // left; mirrored for RTL, where "later in the strip" reads
            // right-to-left instead.
            var fromIndex = thumbList.indexOf(document.querySelector('.aroma-gallery-thumb.active'));
            var toIndex = thumbList.indexOf(thumb);
            var forward = toIndex > fromIndex;
            if (isRtl) { forward = !forward; }
            var exitSign = forward ? -1 : 1; // the outgoing image's exit direction

            main.dataset.sliding = '1';
            main.style.transform = 'translateX(' + (exitSign * 100) + '%)';

            window.setTimeout(function () {
                main.src = full;
                main.alt = newAlt;
                // Instant, invisible jump to the opposite edge, then let the
                // CSS transition (restored by clearing this inline override)
                // carry it back to center — the actual "slide in".
                main.style.transition = 'none';
                main.style.transform = 'translateX(' + (-exitSign * 100) + '%)';
                void main.offsetWidth; // force reflow so the jump above applies before re-enabling the transition
                main.style.transition = '';
                main.style.transform = 'translateX(0)';
                delete main.dataset.sliding;
            }, SLIDE_MS);

            if (caption) {
                caption.textContent = thumb.dataset.label || '';
                caption.classList.toggle('is-empty', !thumb.dataset.label);
            }
            thumbs.forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-current', 'false'); });
            thumb.classList.add('active');
            thumb.setAttribute('aria-current', 'true');
        }

        thumbs.forEach(function (thumb, i) {
            thumb.addEventListener('click', function () { select(thumb); });
            // Arrow-key navigation across the thumbnail strip.
            thumb.addEventListener('keydown', function (e) {
                var next = e.key === 'ArrowRight' ? i + 1 : (e.key === 'ArrowLeft' ? i - 1 : null);
                if (next === null) { return; }
                e.preventDefault();
                var target = thumbs[(next + thumbs.length) % thumbs.length];
                target.focus();
                select(target);
            });
        });
    })();

    // Lightbox — full-size, uncropped view of whichever image the gallery
    // above is currently showing, with its own prev/next (kept in sync with
    // the main gallery by simply clicking the corresponding real thumbnail —
    // reuses all of its existing state-keeping rather than duplicating it).
    (function () {
        var lightbox = document.getElementById('galleryLightbox');
        var main = document.getElementById('galleryMainImg');
        if (!lightbox || !main || typeof bootstrap === 'undefined') { return; }

        var lightboxImg = document.getElementById('lightboxImg');
        var counter = document.getElementById('lightboxCounter');
        var prevBtn = lightbox.querySelector('.aroma-lightbox-prev');
        var nextBtn = lightbox.querySelector('.aroma-lightbox-next');
        var thumbs = Array.prototype.slice.call(document.querySelectorAll('.aroma-gallery-thumb'));

        function activeIndex() {
            var active = document.querySelector('.aroma-gallery-thumb.active');
            var i = thumbs.indexOf(active);
            return i === -1 ? 0 : i;
        }

        function updateCounter() {
            if (counter && thumbs.length && counter.dataset.template) {
                counter.textContent = counter.dataset.template
                    .replace('{n}', activeIndex() + 1).replace('{t}', thumbs.length);
            }
        }

        // On open: main's src/alt are already settled (no slide in flight at
        // that point), so reading them directly is correct here.
        lightbox.addEventListener('show.bs.modal', function () {
            lightboxImg.src = main.getAttribute('src');
            lightboxImg.alt = main.getAttribute('alt') || '';
            updateCounter();
        });

        function step(delta) {
            if (!thumbs.length) { return; }
            var target = thumbs[(activeIndex() + delta + thumbs.length) % thumbs.length];
            // Read the new image straight off the target thumbnail, not off
            // main — main's own src only updates ~320ms into its slide
            // animation (see select() above), so reading it right after
            // target.click() would still return the outgoing image.
            lightboxImg.src = target.dataset.full;
            lightboxImg.alt = target.querySelector('img').alt;
            target.click(); // drives the real thumbnail's own select() — main gallery stays in sync
            updateCounter();
        }

        if (prevBtn) { prevBtn.addEventListener('click', function () { step(-1); }); }
        if (nextBtn) { nextBtn.addEventListener('click', function () { step(1); }); }

        lightbox.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { step(isRtlPage() ? -1 : 1); }
            else if (e.key === 'ArrowLeft') { step(isRtlPage() ? 1 : -1); }
        });

        function isRtlPage() { return document.documentElement.getAttribute('dir') === 'rtl'; }
    })();

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
            var price = checked && checked.dataset.price ? parseFloat(checked.dataset.price) : base;

            document.querySelectorAll('#addToCartForm .js-option-input:checked').forEach(function (o) {
                price += parseFloat(o.dataset.priceDelta) || 0;
            });
            document.querySelectorAll('#addToCartForm .js-option-select').forEach(function (s) {
                var opt = s.options[s.selectedIndex];
                if (opt && opt.value) { price += parseFloat(opt.dataset.priceDelta) || 0; }
            });

            return price;
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
        document.querySelectorAll('#addToCartForm input[name="variant_id"], #addToCartForm .js-option-input, #addToCartForm .js-option-select').forEach(function (r) {
            r.addEventListener('change', render);
        });
        render();
    })();
</script>
@endpush
@endsection
