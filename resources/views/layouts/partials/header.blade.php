@php($locale = app()->getLocale())

<div class="aroma-topbar">
    <div class="container d-flex justify-content-between align-items-center py-1">
        <span class="d-none d-md-inline">{{ __('storefront.trust.delivery') }}</span>
        <span class="aroma-script">{{ __('storefront.hero.title') }}</span>
        @include('layouts.partials.language-switcher')
    </div>
</div>

<nav class="aroma-navbar sticky-top">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between py-3 gap-3">
            {{-- Brand --}}
            <a href="{{ route('home', $locale) }}" class="aroma-logo text-decoration-none">
                {{ $brand['name'] }}
            </a>

            {{-- Search --}}
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
                        <a href="#" class="aroma-icon-link dropdown-toggle text-decoration-none" data-bs-toggle="dropdown" title="{{ __('storefront.nav.account') }}">
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
                    <a href="{{ route('wishlist.index') }}" class="aroma-icon-link" title="{{ __('storefront.nav.wishlist') }}">
                        <i class="bi bi-heart fs-5"></i>
                        @if (count($wishlistIds ?? []) > 0)<span class="aroma-badge">{{ count($wishlistIds) }}</span>@endif
                    </a>
                @else
                    <a href="{{ route('login') }}" class="aroma-icon-link" title="{{ __('storefront.nav.account') }}">
                        <i class="bi bi-person fs-5"></i>
                    </a>
                @endauth
                <a href="{{ route('cart.index') }}" class="aroma-icon-link" title="{{ __('storefront.nav.cart') }}">
                    <i class="bi bi-bag fs-5"></i>
                    @if (($cartCount ?? 0) > 0)<span class="aroma-badge">{{ $cartCount }}</span>@endif
                </a>
            </div>
        </div>

        {{-- Category nav --}}
        <ul class="nav justify-content-center pb-2 flex-wrap">
            @foreach (['perfumes', 'flowers', 'beauty', 'abayas', 'accessories', 'seasonal'] as $cat)
                <li class="nav-item">
                    <a class="nav-link px-3" href="{{ route('category.show', [$locale, $cat]) }}">{{ __('storefront.nav.'.$cat) }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>
