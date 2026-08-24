<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    {{-- Safari detection, as early as possible so aroma.css's html.is-safari
         rules apply before first paint — see the identical script (and full
         explanation) in layouts/app.blade.php. This admin layout loads
         aroma.css too, so it needs the same class for the same Arabic-font
         workaround to take effect here. --}}
    <script>
        (function () {
            var ua = navigator.userAgent;
            var isSafari = /^((?!chrome|android|crios|fxios|edgios|opios|firefox).)*safari/i.test(ua);
            if (isSafari) { document.documentElement.classList.add('is-safari'); }
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Aroma Admin')</title>
    {{-- Back office — must never appear in search results. --}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="{{ \App\Support\Assets::versioned('favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajwal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/aroma.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body class="bg-light">
<div class="d-flex">
    {{-- Sidebar --}}
    <aside class="text-white p-3 vh-100 position-sticky top-0" style="width:250px;background:var(--aroma-ink)">
        <div class="aroma-logo text-center text-white mb-4">{{ $brand['name'] ?? 'Aroma' }}</div>
        <nav class="nav flex-column gap-1">
            @php($items = [
                ['dashboard','bi-speedometer2','Dashboard'],
                ['products','bi-box-seam','Products'],
                ['categories','bi-diagram-3','Categories'],
                ['brands','bi-award','Brands'],
                ['orders','bi-receipt','Orders'],
                ['customers','bi-people','Customers'],
                ['coupons','bi-ticket-perforated','Coupons'],
                ['inventory','bi-boxes','Inventory'],
                ['banners','bi-image','Banners'],
                ['reports','bi-graph-up','Reports'],
            ])
            @foreach ($items as [$key, $icon, $label])
                <a class="nav-link text-white-50 px-2 py-2 rounded {{ ($active ?? '') === $key ? 'bg-secondary text-white' : '' }}" href="#">
                    <i class="bi {{ $icon }} me-2"></i>{{ $label }}
                </a>
            @endforeach
        </nav>
    </aside>

    {{-- Main --}}
    <div class="flex-grow-1">
        <header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">@yield('heading', 'Dashboard')</h5>
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-bell"></i>
                <span class="text-muted small">admin@aroma.sa</span>
            </div>
        </header>
        <main class="p-4">
            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
