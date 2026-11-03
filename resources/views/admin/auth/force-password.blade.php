<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    {{-- See the matching script + comment in layouts/app.blade.php --}}
    <script>
        (function () {
            var ua = navigator.userAgent;
            var isSafari = /^((?!chrome|android|crios|fxios|edgios|opios|firefox).)*safari/i.test(ua)
                || /iP(hone|ad|od)|(CriOS|FxiOS|EdgiOS|OPiOS)/.test(ua)
                || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
            if (isSafari) { document.documentElement.classList.add('is-safari'); }
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('admin.change_password.title') }} — {{ $brand['name'] ?? 'Aroma' }}</title>
    @if (($direction ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/aroma.css') }}" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
</head>
<body data-show-password="{{ __('admin.common.show_password') }}"
      data-hide-password="{{ __('admin.common.hide_password') }}">
<div class="admin-login">
    <div class="admin-login-card">
        <div class="admin-login-brand">
            <div class="mark">{{ $brand['name'] ?? 'Aroma' }}</div>
            <p>
                {{ $forced ? __('admin.change_password.forced_subtitle') : __('admin.change_password.subtitle') }}
            </p>
        </div>

        @if (session('status'))
            <div class="admin-alert admin-alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="admin-alert admin-alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="post" action="{{ route('admin.password.update') }}">
            @csrf
            @method('put')
            <div class="admin-field">
                <label class="admin-label">{{ __('admin.change_password.current') }}</label>
                <input type="password" name="current_password" class="admin-input" autofocus required>
            </div>
            <div class="admin-field">
                <label class="admin-label">{{ __('admin.change_password.new') }}</label>
                <input type="password" name="password" class="admin-input" required>
            </div>
            <div class="admin-field">
                <label class="admin-label">{{ __('admin.change_password.confirm') }}</label>
                <input type="password" name="password_confirmation" class="admin-input" required>
            </div>
            <button type="submit" class="admin-btn admin-btn-primary w-100 justify-content-center">
                <i class="bi bi-shield-lock"></i>{{ __('admin.change_password.submit') }}
            </button>
        </form>

        @unless ($forced)
            <div class="text-center mt-3">
                <a href="{{ route('admin.dashboard') }}" class="admin-back-link">{{ __('admin.back') }}</a>
            </div>
        @endunless
    </div>
</div>
<script src="{{ asset('js/aroma-ui.js') }}"></script>
</body>
</html>
