<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $brand['name'])</title>
    @if (($direction ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/aroma.css') }}" rel="stylesheet">
</head>
<body style="background:var(--aroma-gradient);min-height:100vh">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="text-center mb-4">
                    <a href="{{ route('root') }}" class="aroma-logo text-decoration-none" style="color:#fff">{{ $brand['name'] }}</a>
                    <div class="aroma-script" style="color:var(--aroma-skin)">{{ $brand['tagline'] }}</div>
                </div>

                <div class="card border-0 shadow-lg" style="border-radius:var(--aroma-radius)">
                    <div class="card-body p-4 p-md-5">
                        @include('partials.flash')
                        @yield('content')
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                       class="small text-decoration-none" style="color:#fff">
                        <i class="bi bi-globe2"></i>
                        {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
