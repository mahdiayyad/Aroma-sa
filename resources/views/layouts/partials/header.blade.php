@php($locale = app()->getLocale())

{{-- Centered single-line announcement strip, mockup-style — the language
     switcher moved down into the header's icon row below so this stays a
     single focused message, not a two-sided bar. The icon stays pinned to
     the physical left of the text in both languages (the mockup itself is
     an RTL page and still keeps it there) via .aroma-topbar-line's forced
     ltr direction — a decorative accent glyph, not a "leading icon of the
     sentence" that should flip with reading direction. --}}

<nav class="aroma-navbar sticky-top">
    <div class="container">
        @php($headerLogo = is_file(public_path('images/brand/aroma-wordmark.png'))
                ? 'images/brand/aroma-wordmark.png'
                : (is_file(public_path('images/brand/aroma-logo-mark.png')) ? 'images/brand/aroma-logo-mark.png' : null))
        @php($headerSlogan = is_file(public_path('images/brand/aroma-slogan.png')) ? 'images/brand/aroma-slogan.png' : null)
        {{-- Gold-on-transparent lockup — wordmark + "Awaken your Senses" tagline
             already combined in one image — for the navbar now that its
             background is dark Burgundy (#330101): $headerLogo above is
             burgundy-on-transparent and would be invisible there. Falls back
             to $headerLogo (still visible, just not gold) if the asset is
             ever missing, rather than rendering nothing. Only used for the
             two navbar logo instances below — the offcanvas menu stays on a
             light background, so it keeps the original $headerLogo. --}}
        @php($headerLogoGold = is_file(public_path('images/brand/aroma-wordmark-gold.png')) ? 'images/brand/aroma-wordmark-gold.png' : $headerLogo)

        {{-- Mobile (below lg) — stacked: icon row (menu toggle + account/
             wishlist/cart), then a centered logo row underneath. Nav links
             live in the offcanvas instead; no room for a single desktop-
             style row at phone widths. --}}
        <div class="d-lg-none">
            <div class="d-flex align-items-center pt-2">
                <button class="btn aroma-icon-link border-0 bg-transparent p-1" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#aromaMobileNav" aria-controls="aromaMobileNav"
                        aria-label="{{ __('storefront.nav.menu') }}">
                    <i class="bi bi-list fs-2"></i>
                </button>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    @auth
                        <div class="dropdown">
                            <a href="#" class="aroma-icon-link dropdown-toggle text-decoration-none" data-bs-toggle="dropdown"
                               data-bs-tooltip="true" data-bs-placement="bottom" title="{{ __('storefront.nav.account') }}">
                                <i class="bi bi-person fs-5"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('account.dashboard') }}">{{ __('account.nav.dashboard') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('wishlist.index') }}">{{ __('account.nav.wishlist') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('account.profile.edit') }}">{{ __('account.nav.profile') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">{{ __('account.nav.logout') }}</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                        <a href="{{ route('wishlist.index') }}" class="aroma-icon-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('storefront.nav.wishlist') }}">
                            <i class="bi bi-heart fs-5"></i>
                            <span class="aroma-badge js-wishlist-count {{ count($wishlistIds ?? []) > 0 ? '' : 'd-none' }}">{{ count($wishlistIds ?? []) }}</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="aroma-icon-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('storefront.nav.account') }}">
                            <i class="bi bi-person fs-5"></i>
                        </a>
                    @endauth
                    <a href="{{ route('cart.index') }}" class="aroma-icon-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('storefront.nav.cart') }}">
                        <i class="bi bi-bag fs-5"></i>
                        <span class="aroma-badge js-cart-count {{ ($cartCount ?? 0) > 0 ? '' : 'd-none' }}">{{ $cartCount ?? 0 }}</span>
                    </a>
                </div>
            </div>
            <div class="text-center py-2">
                <a href="{{ route('home', $locale) }}" class="aroma-logo text-decoration-none d-inline-block">
                    @if ($headerLogoGold)
                        {{-- Tagline is already baked into this image — no separate
                             .aroma-logo-slogan-img needed/wanted here. --}}
                        <img src="{{ \App\Support\Assets::versioned($headerLogoGold) }}" alt="{{ $brand['name'] }} — {{ $brand['tagline'] ?? '' }}" class="aroma-logo-img aroma-logo-img-combined">
                    @else
                        {{ $brand['name'] }}
                    @endif
                </a>
            </div>
        </div>

        {{-- Desktop (lg+) — one row, mockup-style: nav links, logo, and
             icons all on the same level instead of three stacked bands.
             A 3-column grid (not flex) keeps the logo genuinely centered
             regardless of how much wider the nav-links column is than the
             icon cluster — flex alternatives (space-between, ms-auto) can't
             do that when the two flanking groups are uneven widths. --}}
        <div class="d-none d-lg-grid aroma-navbar-desktop-row py-3">
            <ul class="nav aroma-navbar-desktop-links">
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->routeIs('home') ? 'active' : '' }}"
                       href="{{ route('home', $locale) }}">{{ __('storefront.nav.home') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="{{ route('home', $locale) }}#categories">{{ __('storefront.nav.categories') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="{{ route('home', $locale) }}#categories">{{ __('storefront.nav.shop') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->routeIs('about') ? 'active' : '' }}"
                       href="{{ route('about') }}">{{ __('storefront.nav.about') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->routeIs('contact') ? 'active' : '' }}"
                       href="{{ route('contact') }}">{{ __('storefront.nav.contact') }}</a>
                </li>
            </ul>

            <a href="{{ route('home', $locale) }}" class="aroma-logo aroma-navbar-desktop-logo text-decoration-none d-inline-block">
                @if ($headerLogoGold)
                    <img src="{{ \App\Support\Assets::versioned($headerLogoGold) }}" alt="{{ $brand['name'] }} — {{ $brand['tagline'] ?? '' }}" class="aroma-logo-img aroma-logo-img-combined">
                @else
                    {{ $brand['name'] }}
                @endif
            </a>

            <div class="d-flex align-items-center gap-3 aroma-navbar-desktop-icons">
                @include('layouts.partials.language-switcher')
                @auth
                    <div class="dropdown">
                        <a href="#" class="aroma-icon-link dropdown-toggle text-decoration-none" data-bs-toggle="dropdown"
                           data-bs-tooltip="true" data-bs-placement="bottom" title="{{ __('storefront.nav.account') }}">
                            <i class="bi bi-person fs-5"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('account.dashboard') }}">{{ __('account.nav.dashboard') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('wishlist.index') }}">{{ __('account.nav.wishlist') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.profile.edit') }}">{{ __('account.nav.profile') }}</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">{{ __('account.nav.logout') }}</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    <a href="{{ route('wishlist.index') }}" class="aroma-icon-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('storefront.nav.wishlist') }}">
                        <i class="bi bi-heart fs-5"></i>
                        <span class="aroma-badge js-wishlist-count {{ count($wishlistIds ?? []) > 0 ? '' : 'd-none' }}">{{ count($wishlistIds ?? []) }}</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="aroma-icon-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('storefront.nav.account') }}">
                        <i class="bi bi-person fs-5"></i>
                    </a>
                @endauth
                <a href="{{ route('cart.index') }}" class="aroma-icon-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('storefront.nav.cart') }}">
                    <i class="bi bi-bag fs-5"></i>
                    <span class="aroma-badge js-cart-count {{ ($cartCount ?? 0) > 0 ? '' : 'd-none' }}">{{ $cartCount ?? 0 }}</span>
                </a>
            </div>
        </div>
    </div>
</nav>

{{-- Mobile offcanvas: categories --}}
<div class="offcanvas offcanvas-start" tabindex="-1" id="aromaMobileNav" aria-labelledby="aromaMobileNavLabel">
    <div class="offcanvas-header">
        <span class="aroma-logo" id="aromaMobileNavLabel">
            @if ($headerLogo ?? null)
                <img src="{{ \App\Support\Assets::versioned($headerLogo) }}" alt="{{ $brand['name'] }}" class="aroma-logo-img">
            @else
                {{ $brand['name'] }}
            @endif
        </span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <ul class="nav flex-column gap-1">
            {{-- Config/lang-driven on purpose (see HomeController) — restore
                 to ['abaya'] alongside AbayaCatalogSeeder if the abaya-only
                 presentation ever comes back. --}}
            {{-- @foreach (['abaya'] as $cat) --}}
            @foreach (['abayas'] as $cat)
                <li class="nav-item">
                    <a class="nav-link aroma-mobile-nav-link {{ request()->is('*/category/'.$cat) ? 'active' : '' }}"
                       href="{{ route('category.show', [$locale, $cat]) }}">{{ __('storefront.nav.'.$cat) }}</a>
                </li>
            @endforeach
        </ul>
        <hr>
        {{-- Reachable here since the header's icon row hides it below lg
             (no room left once account/wishlist/cart are in it). --}}
        @include('layouts.partials.language-switcher')
    </div>
</div>
