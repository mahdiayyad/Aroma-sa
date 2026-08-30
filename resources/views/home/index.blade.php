@extends('layouts.app')

@section('title', $brand['name'].' — '.__('storefront.hero.title'))
@section('meta_description', __('storefront.hero.subtitle'))

@section('content')
    {{-- Hero carousel — each slide is full campaign artwork. Most already
         carry their own baked-in headline, so the caption below is the
         site's short, consistent *actionable* layer (title + CTA) rather
         than a restatement — skipped only on the one slide whose artwork
         already has its own complete "Explore Abayas" button baked in.

         Four slides (signature-style, gifting-set, packaging, fragrance-
         beauty) are deliberately left out of this list: they were cropped
         from a single 2172x724 collage image, so getting each quadrant to
         HeroImageProcessor's uniform 1920x1080 (16:9) meant removing ~43%
         of its width — enough that the baked-in headline text got cut off
         on both edges no matter how the crop was tuned. Re-add them (see
         git history for the exact entries) once real, individually-shot
         source photography replaces that collage. --}}
    @php
        $locale = app()->getLocale();
        $isAr = $locale === 'ar';
        $abayasUrl = route('category.show', [$locale, 'abayas']);
        $giftingUrl = route('home', $locale).'#gifting';
        $heroSlides = [
            [
                'image' => 'images/hero/abaya-rack.jpg',
                'alt'   => $isAr ? 'مجموعة عبايات أروما المعلّقة — أناقة خالدة' : 'The Aroma abayas collection, hung — timeless elegance',
                'url'   => $abayasUrl,
                // No title here — like explore-abayas.jpg below, this artwork
                // already has its own headline baked in ("Timeless Elegance /
                // Abayas Collection"), and a dynamic title on top of it was
                // overlapping/colliding with that baked-in text. Real,
                // clickable CTA is still needed since the image itself isn't one.
                'title' => null,
                'cta'   => $isAr ? 'اختر الآن' : 'Choose Now',
            ],
            [
                'image' => 'images/hero/abaya-models-group.jpg',
                'alt'   => $isAr ? 'عبايات أروما — تصاميم عصرية لكل مناسبة' : 'Aroma abayas — modern designs for every occasion',
                'url'   => $abayasUrl,
                'title' => $isAr ? 'اكتشف تصاميم خالدة' : 'Discover Timeless Designs',
                'cta'   => $isAr ? 'اختر الآن' : 'Choose Now',
            ],
            [
                'image' => 'images/hero/explore-abayas.jpg',
                'alt'   => $isAr ? 'أناقة خالدة، توقيعك المميز — اكتشف العبايات' : 'Timeless elegance, your signature style — explore abayas',
                'url'   => $abayasUrl,
                // No title here — the artwork already has its own headline baked in — but
                // it still needs a real, clickable CTA link (the image itself no longer is one).
                'title' => null,
                'cta'   => $isAr ? 'استكشف العبايات' : 'Explore Abayas',
            ],
            [
                'image' => 'images/hero/abaya-pastels.jpg',
                'alt'   => $isAr ? 'أناقة خالدة، توقيعك المميز' : 'Timeless elegance, your signature style',
                'url'   => $abayasUrl,
                'title' => $isAr ? 'اكتشف الأناقة الهادئة' : 'Discover Soft Elegance',
                'cta'   => $isAr ? 'اختر الآن' : 'Choose Now',
            ],
            [
                'image' => 'images/hero/gift-exchange.jpg',
                'alt'   => $isAr ? 'هدية مميزة ملفوفة بأناقة' : 'A thoughtful gift, wrapped in elegance',
                'url'   => $giftingUrl,
                'title' => $isAr ? 'اكتشف الهدية المثالية' : 'Discover the Perfect Gift',
                'cta'   => $isAr ? 'أهدِ الآن' : 'Gift Now',
            ],
        ];
    @endphp
    <section class="container mt-4">
        <div id="aromaHeroCarousel" class="carousel slide aroma-hero-carousel aroma-animate-in"
             data-bs-ride="carousel" data-bs-pause="hover" data-bs-touch="true"
             aria-label="{{ __('storefront.hero.title') }}">
            <div class="carousel-indicators">
                @foreach ($heroSlides as $i => $slide)
                    <button type="button" data-bs-target="#aromaHeroCarousel" data-bs-slide-to="{{ $i }}"
                            class="{{ $i === 0 ? 'active' : '' }}" {{ $i === 0 ? 'aria-current=true' : '' }}
                            aria-label="{{ __('storefront.hero.slide') }} {{ $i + 1 }}"></button>
                @endforeach
            </div>

            <div class="carousel-inner">
                @foreach ($heroSlides as $i => $slide)
                    <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                        {{-- The slide itself is not a link — only the CTA button is
                             clickable. An <a> wrapping the whole image is natively
                             draggable in every browser, which fights the custom
                             drag-to-navigate handling in aroma-ui.js. --}}
                        <div class="aroma-hero-slide">
                            <img src="{{ \App\Support\Assets::versioned($slide['image']) }}" alt="{{ $slide['alt'] }}"
                                 loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                 {{ $i === 0 ? 'fetchpriority=high' : '' }}>
                            @if ($slide['cta'])
                                <div class="aroma-hero-slide-caption">
                                    @if ($slide['title'])
                                        <p class="aroma-hero-slide-title">{{ $slide['title'] }}</p>
                                    @endif
                                    <a href="{{ $slide['url'] }}" class="aroma-hero-slide-cta">
                                        {{ $slide['cta'] }}
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#aromaHeroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">{{ __('storefront.hero.prev') }}</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#aromaHeroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">{{ __('storefront.hero.next') }}</span>
            </button>
        </div>
    </section>

    {{-- Perks row — mockup-style: a plain 4-item icon row right under the
         hero, no card/border. Payment-method trust signals aren't lost by
         moving away from the old payments/BNPL copy here — the footer's
         payment-icon-chip row already carries that. --}}
    <section class="container aroma-perks-row">
        <div class="aroma-perk">
            <i class="bi bi-gift" aria-hidden="true"></i>
            <p>{{ __('storefront.trust.gift_wrap') }}</p>
        </div>
        <div class="aroma-perk">
            <i class="bi bi-award" aria-hidden="true"></i>
            <p>{{ __('storefront.trust.curated') }}</p>
        </div>
        <div class="aroma-perk">
            <i class="bi bi-truck" aria-hidden="true"></i>
            <p>{{ __('storefront.trust.delivery') }}</p>
        </div>
        <div class="aroma-perk">
            <i class="bi bi-percent" aria-hidden="true"></i>
            <p>{{ __('storefront.trust.offers') }}</p>
        </div>
    </section>

    {{-- Categories --}}
    <section class="container aroma-section" id="categories">
        <h2 class="aroma-section-title">{{ __('storefront.sections.categories') }}</h2>
        <div class="row g-4">
            @foreach ($featuredCategories as $category)
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('category.show', [app()->getLocale(), $category->slug]) }}" class="text-decoration-none">
                        @if ($category->image)
                            {{-- Photo tile: real category photography, per the
                                 mockup, with a solid caption bar rather than
                                 text overlaid straight on the image. --}}
                            <div class="aroma-category-card aroma-category-card-photo">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image) }}"
                                     alt="{{ $category->name }}" loading="lazy" class="aroma-category-card-img">
                                <div class="aroma-category-card-caption">
                                    <span class="fw-semibold">{{ $category->name }}</span>
                                    <i class="bi {{ app()->getLocale() === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }}" aria-hidden="true"></i>
                                </div>
                            </div>
                        @else
                            {{-- No photo on file for this category yet — the
                                 original icon-in-a-box tile, restyled. --}}
                            <div class="aroma-category-card text-center">
                                <div class="card-body py-4">
                                    <i class="bi {{ $category->icon ?? 'bi-tag' }} fs-1 d-block mb-2 text-aroma-brown"></i>
                                    <span class="fw-semibold">{{ $category->name }}</span>
                                </div>
                            </div>
                        @endif
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

    {{-- Gifting spotlight — mockup-driven: centered offer copy flanked by the
         brand's hand-drawn product/botanical pattern bleeding off both edges,
         instead of the old two-column text+icon layout. --}}
    <section class="container aroma-section" id="gifting" style="scroll-margin-top:90px">
        <div class="aroma-promo-panel aroma-trust p-4 p-md-5 text-center">
            <div class="aroma-promo-pattern aroma-promo-pattern-start" aria-hidden="true"></div>
            <div class="aroma-promo-pattern aroma-promo-pattern-end" aria-hidden="true"></div>
            <div class="aroma-promo-content">
                <h2 class="aroma-section-title">{{ __('storefront.sections.gifting') }}</h2>
                <p class="fs-5 mb-2">{{ __('storefront.gifting.headline') }}</p>
                <p class="text-aroma-muted mb-3">{{ __('storefront.gifting.body') }}</p>
                <a href="#" class="btn btn-aroma">{{ __('storefront.gifting.cta') }}</a>
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
