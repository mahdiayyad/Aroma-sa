@extends('layouts.auth')

@section('title', __('auth_ui.login.title').' — '.$brand['name'])

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/otp.css') }}">
@endpush

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.login.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.login.subtitle') }}</p>

    <div class="aroma-auth-toggle" role="tablist">
        <input type="radio" class="btn-check" name="authMethod" id="authMethodPassword" checked>
        <label class="btn btn-aroma-outline" for="authMethodPassword" data-auth-tab="password">{{ __('otp.tab_password') }}</label>

        <input type="radio" class="btn-check" name="authMethod" id="authMethodPhone">
        <label class="btn btn-aroma-outline" for="authMethodPhone" data-auth-tab="phone">{{ __('otp.tab_phone') }}</label>
    </div>

    <div id="passwordLoginPanel">
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">{{ __('auth_ui.login.login') }}</label>
                <input type="text" name="login" value="{{ old('login') }}"
                       class="form-control @error('login') is-invalid @enderror" required autofocus>
                @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label class="form-label">{{ __('auth_ui.login.password') }}</label>
                    <a href="{{ route('password.request') }}" class="small">{{ __('auth_ui.login.forgot') }}</a>
                </div>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
                <label for="remember" class="form-check-label">{{ __('auth_ui.login.remember') }}</label>
            </div>

            <button type="submit" class="btn btn-aroma w-100 mb-3">{{ __('auth_ui.login.submit') }}</button>
        </form>
    </div>

    @include('auth.partials.otp-panel')

    @include('auth.partials.social')

    <p class="text-center mb-0 mt-3">
        {{ __('auth_ui.login.no_account') }}
        <a href="{{ route('register') }}">{{ __('auth_ui.login.register_link') }}</a>
    </p>
@endsection

@push('scripts')
<script src="{{ \App\Support\Assets::versioned('js/otp-auth.js') }}"></script>
<script>
    (function () {
        var passwordTab = document.getElementById('authMethodPassword');
        var phoneTab = document.getElementById('authMethodPhone');
        var passwordPanel = document.getElementById('passwordLoginPanel');
        var otpPanel = document.getElementById('otpLoginPanel');
        if (!passwordTab || !phoneTab) { return; }

        passwordTab.addEventListener('change', function () {
            if (this.checked) {
                passwordPanel.classList.remove('d-none');
                otpPanel.classList.add('d-none');
            }
        });
        phoneTab.addEventListener('change', function () {
            if (this.checked) {
                otpPanel.classList.remove('d-none');
                passwordPanel.classList.add('d-none');
            }
        });
    })();
</script>
@endpush
