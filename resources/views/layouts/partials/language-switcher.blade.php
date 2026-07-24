@php
    $current   = app()->getLocale();
    $supported = array_keys($locales);
    // Storefront URLs are /{locale}/… — switching means swapping that segment.
    // Non-prefixed pages (cart, checkout, account) have no locale in the URL, so
    // they go through locale.switch which persists the choice in the session.
    $segments   = explode('/', request()->path()); // 'en/product/x' → ['en','product','x']
    $isPrefixed = isset($segments[0]) && in_array($segments[0], $supported, true);
    $query      = request()->getQueryString();
@endphp
<div class="dropdown">
    <button class="btn btn-sm dropdown-toggle aroma-icon-link border-0 bg-transparent" type="button"
            data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-globe2 me-1"></i>{{ $locales[$current]['native'] ?? strtoupper($current) }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach ($locales as $code => $meta)
            @php
                if ($isPrefixed) {
                    $segs = $segments;
                    $segs[0] = $code;
                    $href = url(implode('/', $segs)).($query ? '?'.$query : '');
                } else {
                    $href = route('locale.switch', $code);
                }
            @endphp
            <li>
                <a class="dropdown-item {{ $code === $current ? 'active' : '' }}" href="{{ $href }}">
                    {{ $meta['native'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
