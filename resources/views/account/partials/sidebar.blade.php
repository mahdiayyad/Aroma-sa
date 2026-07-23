@php($active = $active ?? 'dashboard')
<div class="aroma-trust p-3">
    <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
              style="width:44px;height:44px;background:var(--aroma-brown)">{{ auth()->user()->initials() }}</span>
        <div>
            <div class="fw-semibold">{{ auth()->user()->name }}</div>
            <div class="small text-muted">{{ auth()->user()->email ?? auth()->user()->phone }}</div>
        </div>
    </div>
    <nav class="nav flex-column gap-1">
        <a class="nav-link px-2 rounded {{ $active === 'dashboard' ? 'text-white' : '' }}"
           style="{{ $active === 'dashboard' ? 'background:var(--aroma-brown)' : 'color:var(--aroma-ink)' }}"
           href="{{ route('account.dashboard') }}"><i class="bi bi-grid me-2"></i>{{ __('account.nav.dashboard') }}</a>
        <a class="nav-link px-2 rounded {{ $active === 'wishlist' ? 'text-white' : '' }}"
           style="{{ $active === 'wishlist' ? 'background:var(--aroma-brown)' : 'color:var(--aroma-ink)' }}"
           href="{{ route('wishlist.index') }}"><i class="bi bi-heart me-2"></i>{{ __('account.nav.wishlist') }}</a>
        <a class="nav-link px-2 rounded {{ $active === 'profile' ? 'text-white' : '' }}"
           style="{{ $active === 'profile' ? 'background:var(--aroma-brown)' : 'color:var(--aroma-ink)' }}"
           href="{{ route('account.profile.edit') }}"><i class="bi bi-person-gear me-2"></i>{{ __('account.nav.profile') }}</a>
        <form method="post" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button type="submit" class="btn btn-aroma-outline btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i>{{ __('account.nav.logout') }}
            </button>
        </form>
    </nav>
</div>
