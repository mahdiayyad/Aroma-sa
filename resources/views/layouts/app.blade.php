<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    {{-- Safari detection, as early as possible so aroma.css's html.is-safari
         rules apply before first paint (no flash of the wrong font). The
         licensed AligarhArabic file on hand is a trial build whose Arabic
         shaping renders visibly broken specifically on Safari — see the note
         next to --aroma-heading-ar in aroma.css. Excludes the other browsers
         that also carry "Safari" in their UA on iOS (Chrome/Firefox/Edge/
         Opera), where WebKit is required by Apple but the bug doesn't show. --}}
    <script>
        (function () {
            var ua = navigator.userAgent;
            var isSafari = /^((?!chrome|android|crios|fxios|edgios|opios|firefox).)*safari/i.test(ua);
            if (isSafari) { document.documentElement.classList.add('is-safari'); }
        })();
    </script>
    {{-- Scroll-reveal opt-in, same "as early as possible" reasoning as the
         Safari check above — this is what lets animations.css hide
         .aroma-reveal elements pre-scroll without a flash-of-visible-then-
         hidden content: the class is on <html> before first paint, so the
         hidden state is what actually paints first. The class is added ONLY
         when IntersectionObserver exists — if it doesn't (or JS never runs
         at all), .aroma-reveal elements simply stay at their normal, fully
         visible CSS state forever; nothing in this app depends on the
         separate deferred public/js/scroll-reveal.js running to be seen. --}}
    <script>
        if ('IntersectionObserver' in window) { document.documentElement.classList.add('js-reveal-ready'); }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $brand['name'].' — '.$brand['tagline'])</title>
    <meta name="description" content="@yield('meta_description', $brand['tagline'])">
    @if (trim($__env->yieldContent('meta_keywords')) !== '')
        <meta name="keywords" content="@yield('meta_keywords')">
    @endif
    <meta name="robots" content="@yield('robots', 'index, follow')">

    {{-- Favicon / app icons — regenerated from the current logo via
         `php artisan aroma:prepare-favicon` (see
         App\Console\Commands\PrepareFaviconCommand), which crops the "A"
         glyph out of public/images/brand/aroma-logo-mark.png.
         This is also what Google Search shows next to the site, so it follows
         Google's favicon guidelines: only multiples of 48px are advertised
         (never 16x16 — Google scales it down itself; browsers scale the 48px
         and the .ico's 16/32 frames for the tab), and the URLs are stable —
         fingerprinted by file content, not mtime, so they change only when the
         icon does. After changing the icon, Google needs a recrawl of the
         home page (Search Console → URL inspection → Request indexing). --}}
    <link rel="icon" href="{{ \App\Support\Assets::stable('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ \App\Support\Assets::stable('favicon-48x48.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ \App\Support\Assets::stable('android-chrome-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ \App\Support\Assets::stable('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#704F2F">

    {{-- Canonical + hreflang (only advertised where a real per-locale URL
         exists — see App\Support\Seo::alternateUrls()). --}}
    @php($alternates = \App\Support\Seo::alternateUrls())
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach ($alternates as $altLocale => $altUrl)
        <link rel="alternate" hreflang="{{ $altLocale }}" href="{{ $altUrl }}">
    @endforeach
    @if (count($alternates))
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[config('aroma.default_locale')] ?? url()->current() }}">
    @endif

    {{-- Open Graph / Twitter Card --}}
    <meta property="og:site_name" content="{{ $brand['name'] }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', $brand['name'].' — '.$brand['tagline'])">
    <meta property="og:description" content="@yield('meta_description', $brand['tagline'])">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', \App\Support\Assets::versioned('android-chrome-512x512.png'))">
    <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US' }}">
    @foreach (array_keys(config('aroma.locales', [])) as $ogLocale)
        @if ($ogLocale !== app()->getLocale())
            <meta property="og:locale:alternate" content="{{ $ogLocale === 'ar' ? 'ar_SA' : 'en_US' }}">
        @endif
    @endforeach
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $brand['name'].' — '.$brand['tagline'])">
    <meta name="twitter:description" content="@yield('meta_description', $brand['tagline'])">
    <meta name="twitter:image" content="@yield('og_image', \App\Support\Assets::versioned('android-chrome-512x512.png'))">

    {{-- Structured data: Organization + WebSite, present on every page.
         Page-specific schema (Product, BreadcrumbList) is pushed onto
         'structured_data' by the views that have it. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/').'#organization',
                    'name' => $brand['name'],
                    'url' => url('/'),
                    'logo' => \App\Support\Assets::versioned('android-chrome-512x512.png'),
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'name' => $brand['name'],
                    'url' => url('/'),
                    'publisher' => ['@id' => url('/').'#organization'],
                    'inLanguage' => [app()->getLocale() === 'ar' ? 'ar-SA' : 'en'],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @stack('structured_data')

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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Inter:wght@300;400;500;600;700;800&family=Tangerine:wght@400;700&family=El+Messiri:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Aref+Ruqaa:wght@400;700&display=swap" rel="stylesheet">
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
    <link href="{{ \App\Support\Assets::versioned('css/components/hero-carousel.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.8.2/build/css/intlTelInput.min.css" rel="stylesheet">
    <link href="{{ \App\Support\Assets::versioned('css/components/intl-phone.css') }}" rel="stylesheet">

    @stack('head')
</head>
<body data-cart-url="{{ route('cart.index') }}"
      data-cart-label="{{ __('cart.view') }}"
      data-cart-error="{{ __('cart.error') }}"
      data-wishlist-url="{{ route('wishlist.index') }}"
      data-wishlist-label="{{ __('storefront.nav.wishlist') }}"
      data-confirm-yes="{{ __('storefront.confirm.yes') }}"
      data-confirm-cancel="{{ __('storefront.confirm.cancel') }}"
      data-flash-success="{{ session('status') }}"
      data-show-password="{{ __('auth_ui.show_password') }}"
      data-hide-password="{{ __('auth_ui.hide_password') }}">
      
    {{-- "Verified store" seal (Saudi Business Center): seal.js pins a fixed 120x44px iframe bottom-left.
         public/css/assistant.css keeps the chat above it (lifted while closed, layered over it while
         open) — revisit that file if the seal's position or size ever changes. --}}
    <div class="sbc-verify-seal" data-token="NmU3U1ZDbCtNMGIxb0M1V01IWVlGQT09" data-position="bottom-left"></div>
    <script src="https://eauthenticate.saudibusiness.gov.sa/EAuthSealApi/seal.js" async></script>
    
    @include('layouts.partials.intro')
    @include('layouts.partials.admin-preview-banner')
    @include('layouts.partials.header')

    <main>
        @if (session('status') || session('error') || session('warning') || $errors->any())
            <div class="container mt-3">@include('partials.flash')</div>
        @endif
        @yield('content')
    </main>

    @include('layouts.partials.footer')
    @include('layouts.partials.cart-modal')
    @include('layouts.partials.assistant')

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/select2-init.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/aroma-http.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/cart-modal.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/aroma-ui.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/scroll-reveal.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.8.2/build/js/intlTelInputWithUtils.min.js"></script>
    <script src="{{ \App\Support\Assets::versioned('js/intl-phone.js') }}"></script>
    <script src="{{ \App\Support\Assets::versioned('js/assistant.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
