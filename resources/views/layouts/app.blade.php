<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $brand['name'].' — '.$brand['tagline'])</title>
    <meta name="description" content="@yield('meta_description', $brand['tagline'])">

    {{-- Bootstrap 5.3 (RTL build for Arabic) served from CDN so the app renders
         without a Node build step. The production SCSS pipeline emits the same
         into public/css when compiled. --}}
    @if (($direction ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Brand typography fallbacks (licensed Manier/Luxury/Helvetica Neue LT
         Arabic files aren't in the repo yet — see public/fonts/README.md).
         Playfair Display + Inter stand in for English headings/body; El
         Messiri + IBM Plex Sans Arabic stand in for Arabic headings/body;
         Tangerine stands in for the Snell Roundhand script accent. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Inter:wght@300;400;500;600;700;800&family=Tangerine:wght@400;700&family=El+Messiri:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    {{-- ?v=<mtime> so a changed stylesheet is never served from browser cache. --}}
    <link href="{{ \App\Support\Assets::versioned('css/aroma.css') }}" rel="stylesheet">

    {{-- Fetch the loading-animation artwork at high priority so the reveal can
         start immediately instead of waiting on a lazily-discovered <img>. --}}
    @if (is_file(public_path('images/brand/aroma-wordmark.png')))
        <link rel="preload" as="image" href="{{ \App\Support\Assets::versioned('images/brand/aroma-wordmark.png') }}">
        <link rel="preload" as="image" href="{{ \App\Support\Assets::versioned('images/brand/aroma-slogan.png') }}">
    @endif

    {{-- Component library (ai-docs/UI_UX_GUIDELINES.md's requested modular
         CSS architecture) — hand-authored, no build step, mirrors aroma.css's
         own approach. tokens.css must load first; the rest are order-independent. --}}
    <link href="{{ \App\Support\Assets::versioned('css/components/tokens.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/buttons.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/forms.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="{{ asset('css/components/select2.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/cards.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/badges-alerts.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/tables.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/navigation.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/animations.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\Assets::versioned('css/cart-modal.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\Assets::versioned('css/assistant.css') }}" rel="stylesheet">

    @stack('head')
</head>
<body data-cart-url="{{ route('cart.index') }}"
      data-cart-label="{{ __('cart.view') }}"
      data-cart-error="{{ __('cart.error') }}"
      data-wishlist-url="{{ route('wishlist.index') }}"
      data-wishlist-label="{{ __('storefront.nav.wishlist') }}">
    @include('layouts.partials.intro')
    @include('layouts.partials.header')

    <main>
        @if (session('status') || $errors->any())
            <div class="container mt-3">@include('partials.flash')</div>
        @endif
        @yield('content')
    </main>

    @include('layouts.partials.footer')
    @include('layouts.partials.cart-modal')
    @include('layouts.partials.assistant')

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/select2-init.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/aroma-http.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/cart-modal.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/aroma-ui.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/assistant.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
