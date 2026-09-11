<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    {{-- See the matching script + comment in layouts/app.blade.php — flags
         Safari before first paint so aroma.css's html.is-safari Arabic font
         override (working around a broken trial-font build) applies here too. --}}
    <script>
        (function () {
            var ua = navigator.userAgent;
            var isSafari = /^((?!chrome|android|crios|fxios|edgios|opios|firefox).)*safari/i.test(ua);
            if (isSafari) { document.documentElement.classList.add('is-safari'); }
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.app_name'))</title>

    @if (($direction ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Inter leads the input font stack (see --aroma-input-font); it must be
         available here too, or typed symbols fall back to the Arabic face. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/aroma.css') }}" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet">
    @stack('head')
</head>
<body class="admin-body" data-confirm-yes="{{ __('admin.common.delete') }}" data-confirm-cancel="{{ __('admin.common.cancel') }}"
      data-flash-success="{{ session('status') }}" data-error-generic="{{ __('admin.common.error_generic') }}">
    <div class="admin-shell" id="adminShell">
        @include('admin.layouts.sidebar')

        <div class="admin-main">
            @include('admin.layouts.topbar')

            <main class="admin-content">
                @include('admin.partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    <div class="admin-backdrop" id="adminBackdrop"></div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    <script src="{{ asset('js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
