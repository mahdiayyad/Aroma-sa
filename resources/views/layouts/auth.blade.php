<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $brand['name'])</title>
    {{-- Auth pages carry no unique content worth ranking — keep them out of
         search results (they'd otherwise duplicate/compete with the storefront). --}}
    <meta name="robots" content="noindex, follow">
    <link rel="icon" type="image/x-icon" href="{{ \App\Support\Assets::versioned('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\Assets::versioned('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    @if (($direction ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Inter:wght@300;400;500;600;700;800&family=Tangerine:wght@400;700&family=El+Messiri:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/aroma.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/tokens.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/buttons.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/forms.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="{{ asset('css/components/select2.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/badges-alerts.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/navigation.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/animations.css') }}" rel="stylesheet">
</head>
<body class="aroma-auth-shell" data-flash-success="{{ session('status') }}"
      data-show-password="{{ __('auth_ui.show_password') }}"
      data-hide-password="{{ __('auth_ui.hide_password') }}">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="text-center mb-4">
                    <a href="{{ route('root') }}" class="aroma-logo aroma-auth-brand text-decoration-none">{{ $brand['name'] }}</a>
                    <div class="aroma-script aroma-auth-tagline">{{ $brand['tagline'] }}</div>
                </div>

                <div class="card aroma-auth-card">
                    <div class="card-body p-4 p-md-5">
                        @include('partials.flash')
                        @yield('content')
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                       class="small text-decoration-none aroma-auth-locale-link">
                        <i class="bi bi-globe2"></i>
                        {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/select2-init.js') }}"></script>
    <script src="{{ asset('js/aroma-ui.js') }}"></script>
    @stack('scripts')
</body>
</html>
