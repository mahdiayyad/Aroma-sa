@php
    $current   = app()->getLocale();
    $supported = array_keys($locales);
    // Exactly 2 supported locales — a direct toggle to "the other one", not a
    // menu. Mirrors layouts/auth.blade.php's own locale link for consistency.
    $other = collect($supported)->first(fn ($code) => $code !== $current) ?? $current;

    // Storefront URLs are /{locale}/… — switching means swapping that segment.
    // Non-prefixed pages (cart, checkout, account) have no locale in the URL, so
    // they go through locale.switch which persists the choice in the session.
    $segments   = explode('/', request()->path()); // 'en/product/x' → ['en','product','x']
    $isPrefixed = isset($segments[0]) && in_array($segments[0], $supported, true);
    $query      = request()->getQueryString();

    if ($isPrefixed) {
        $segs = $segments;
        $segs[0] = $other;
        $href = url(implode('/', $segs)).($query ? '?'.$query : '');
    } else {
        $href = route('locale.switch', $other);
    }
@endphp
<a href="{{ $href }}" class="btn btn-sm aroma-icon-link border-0 bg-transparent text-decoration-none">
    <i class="bi bi-globe2 me-1"></i>{{ $locales[$other]['native'] ?? strtoupper($other) }}
</a>
