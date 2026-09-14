@php($active = $active ?? 'dashboard')
<div class="aroma-trust p-3">
    <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
        <span class="aroma-avatar">{{ auth()->user()->initials() }}</span>
        <div>
            <div class="fw-semibold">{{ auth()->user()->name }}</div>
            <div class="small text-aroma-muted" dir="ltr">{{ auth()->user()->email ?? auth()->user()->phone }}</div>
        </div>
    </div>
    <nav class="nav flex-column gap-1">
        <a class="aroma-account-nav-link {{ $active === 'dashboard' ? 'active' : '' }}"
           href="{{ route('account.dashboard') }}"><i class="bi bi-grid me-2"></i>{{ __('account.nav.dashboard') }}</a>
        <a class="aroma-account-nav-link {{ $active === 'orders' ? 'active' : '' }}"
           href="{{ route('order.index') }}"><i class="bi bi-receipt me-2"></i>{{ __('account.nav.orders') }}</a>
        <a class="aroma-account-nav-link {{ $active === 'wishlist' ? 'active' : '' }}"
           href="{{ route('wishlist.index') }}"><i class="bi bi-heart me-2"></i>{{ __('account.nav.wishlist') }}</a>
        <a class="aroma-account-nav-link {{ $active === 'referrals' ? 'active' : '' }}"
           href="{{ route('account.referrals.index') }}"><i class="bi bi-gift me-2"></i>{{ __('account.nav.referrals') }}</a>
        <a class="aroma-account-nav-link {{ $active === 'addresses' ? 'active' : '' }}"
           href="{{ route('account.addresses.index') }}"><i class="bi bi-geo-alt me-2"></i>{{ __('account.nav.addresses') }}</a>
        <a class="aroma-account-nav-link {{ $active === 'profile' ? 'active' : '' }}"
           href="{{ route('account.profile.edit') }}"><i class="bi bi-person-gear me-2"></i>{{ __('account.nav.profile') }}</a>
        <form method="post" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button type="submit" class="btn btn-aroma-outline btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i>{{ __('account.nav.logout') }}
            </button>
        </form>
    </nav>
</div>
