{{-- Shared tab strip across the 4 Customer Guide detail pages — lets a
     shopper jump between guides without returning to the hub each time.
     $active is one of: sizing, fit, care, returns (passed by the includer). --}}
@php
    $guideTabs = [
        'sizing'  => ['route' => 'guides.sizing',  'icon' => 'bi-rulers'],
        'fit'     => ['route' => 'guides.fit',     'icon' => 'bi-gem'],
        'care'    => ['route' => 'guides.care',    'icon' => 'bi-droplet'],
        'returns' => ['route' => 'guides.returns', 'icon' => 'bi-arrow-repeat'],
    ];
@endphp
<nav class="aroma-guide-subnav" aria-label="{{ $isAr ? 'دليل العميل' : 'Customer Guide' }}">
    @foreach ($guideTabs as $key => $tab)
        <a href="{{ route($tab['route']) }}" class="aroma-guide-tab {{ $active === $key ? 'is-active' : '' }}">
            <i class="bi {{ $tab['icon'] }}" aria-hidden="true"></i>{{ __('guides.nav.'.$key) }}
        </a>
    @endforeach
</nav>
