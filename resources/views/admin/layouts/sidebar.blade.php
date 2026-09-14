@php($nav = [
    ['route' => 'admin.dashboard',      'match' => 'admin.dashboard',  'icon' => 'bi-grid-1x2',   'label' => __('admin.nav.dashboard')],
    ['route' => 'admin.products.index', 'match' => 'admin.products.*', 'icon' => 'bi-box-seam',    'label' => __('admin.nav.products')],
    ['route' => 'admin.categories.index','match' => 'admin.categories.*','icon' => 'bi-diagram-3', 'label' => __('admin.nav.categories')],
    ['route' => 'admin.brands.index',   'match' => 'admin.brands.*',   'icon' => 'bi-award',       'label' => __('admin.nav.brands')],
    ['route' => 'admin.gift-cards.index','match' => 'admin.gift-cards.*','icon' => 'bi-postcard', 'label' => __('admin.nav.gift_cards')],
    ['route' => 'admin.promo-codes.index','match' => 'admin.promo-codes.*','icon' => 'bi-ticket-perforated', 'label' => __('admin.nav.promo_codes')],
    ['route' => 'admin.reviews.index',  'match' => 'admin.reviews.*',  'icon' => 'bi-chat-square-text', 'label' => __('admin.nav.reviews')],
    ['route' => 'admin.orders.index',   'match' => 'admin.orders.*',   'icon' => 'bi-receipt',     'label' => __('admin.nav.orders')],
    ['route' => 'admin.customers.index','match' => 'admin.customers.*','icon' => 'bi-people',      'label' => __('admin.nav.customers')],
])

<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="admin-brand-mark">
            {{ $brand['name'] ?? 'Aroma' }}<span>{{ __('admin.badge') }}</span>
        </a>
    </div>

    <nav class="admin-nav">
        <div class="admin-nav-section">{{ __('admin.nav.manage') }}</div>
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}"
               class="admin-nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                <i class="bi {{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="admin-sidebar-foot">
        <a href="{{ route('home', app()->getLocale()) }}" class="admin-nav-link" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right"></i><span>{{ __('admin.view_store') }}</span>
        </a>
    </div>
</aside>
