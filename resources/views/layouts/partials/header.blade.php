@php($locale = app()->getLocale())

<div class="aroma-topbar">
    <div class="container d-flex justify-content-between align-items-center py-1">
        {{-- <span class="d-none d-md-inline">{{ __('storefront.trust.delivery') }}</span> --}}
        <span class="aroma-script">{{ __('storefront.hero.title') }}</span>
        @include('layouts.partials.language-switcher')
    </div>
</div>

<nav class="aroma-navbar sticky-top">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between py-3 gap-3">
            <div class="d-flex align-items-center gap-2">
                {{-- Mobile menu toggle (search + categories live in the offcanvas below lg) --}}
                <button class="btn d-lg-none aroma-icon-link border-0 bg-transparent p-1" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#aromaMobileNav" aria-controls="aromaMobileNav"
                        aria-label="{{ __('storefront.nav.menu') }}">
                    <i class="bi bi-list fs-2"></i>
                </button>

                {{-- Official logotype when the transparent asset exists
                     (php artisan aroma:prepare-logo), otherwise the wordmark. --}}
                @php($headerLogo = is_file(public_path('images/brand/aroma-wordmark.png'))
                        ? 'images/brand/aroma-wordmark.png'
                        : (is_file(public_path('images/brand/aroma-logo-mark.png')) ? 'images/brand/aroma-logo-mark.png' : null))
                <a href="{{ route('home', $locale) }}" class="aroma-logo text-decoration-none">
                    @if ($headerLogo)
                        <img src="{{ \App\Support\Assets::versioned($headerLogo) }}" alt="{{ $brand['name'] }}" class="aroma-logo-img">
                    @else
                        {{ $brand['name'] }}
                    @endif
                </a>
            </div>

            {{-- Search (desktop) --}}
            <form class="aroma-search flex-grow-1 d-none d-lg-block" role="search"
                  action="{{ route('home', $locale) }}" method="get">
                <div class="input-group">
                    <input type="search" name="q" class="form-control"
                           placeholder="{{ __('storefront.nav.search') }}"
                           aria-label="{{ __('storefront.nav.search') }}">
                    <button class="btn btn-aroma" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>

            {{-- Account / wishlist / cart --}}
            <div class="d-flex align-items-center gap-3">
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

        {{-- Category nav (desktop) --}}
        <ul class="nav justify-content-center pb-2 d-none d-lg-flex">
            @foreach (['abayas'] as $cat)
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->is('*/category/'.$cat) ? 'active' : '' }}"
                       href="{{ route('category.show', [$locale, $cat]) }}">{{ __('storefront.nav.'.$cat) }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>

{{-- Mobile offcanvas: search + categories --}}
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
        <form class="aroma-search mb-4" role="search" action="{{ route('home', $locale) }}" method="get">
            <div class="input-group">
                <input type="search" name="q" class="form-control"
                       placeholder="{{ __('storefront.nav.search') }}"
                       aria-label="{{ __('storefront.nav.search') }}">
                <button class="btn btn-aroma" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
        <ul class="nav flex-column gap-1">
            @foreach (['abayas'] as $cat)
                <li class="nav-item">
                    <a class="nav-link aroma-mobile-nav-link {{ request()->is('*/category/'.$cat) ? 'active' : '' }}"
                       href="{{ route('category.show', [$locale, $cat]) }}">{{ __('storefront.nav.'.$cat) }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
