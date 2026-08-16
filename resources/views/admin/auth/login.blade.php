<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('admin.login.title') }} — {{ $brand['name'] ?? 'Aroma' }}</title>
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
            <p>{{ __('admin.login.subtitle') }}</p>
        </div>

        @if ($errors->any())
            <div class="admin-alert admin-alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="post" action="{{ route('admin.login.attempt') }}">
            @csrf
            <div class="admin-field">
                <label class="admin-label">{{ __('admin.login.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="admin-input" autofocus required>
            </div>
            <div class="admin-field">
                <label class="admin-label">{{ __('admin.login.password') }}</label>
                <input type="password" name="password" class="admin-input" required>
            </div>
            <label class="admin-switch mb-3">
                <input type="checkbox" name="remember" value="1"> {{ __('admin.login.remember') }}
            </label>
            <button type="submit" class="admin-btn admin-btn-primary w-100 justify-content-center">
                <i class="bi bi-box-arrow-in-right"></i>{{ __('admin.login.submit') }}
            </button>
        </form>
    </div>
</div>
<script src="{{ asset('js/aroma-ui.js') }}"></script>
</body>
</html>
