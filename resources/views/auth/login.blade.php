@extends('layouts.auth')

@section('title', __('auth_ui.login.title').' — '.$brand['name'])

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/otp.css') }}">
@endpush

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.login.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.login.subtitle') }}</p>

    <div class="aroma-segmented aroma-segmented-block" role="tablist">
        <input type="radio" class="btn-check" name="authMethod" id="authMethodPassword" checked>
        <label class="aroma-segmented-option" for="authMethodPassword" data-auth-tab="password">{{ __('otp.tab_password') }}</label>

        <input type="radio" class="btn-check" name="authMethod" id="authMethodPhone">
        <label class="aroma-segmented-option" for="authMethodPhone" data-auth-tab="phone">{{ __('otp.tab_phone') }}</label>
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

            <div class="mb-4">
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

        // Soft fade-in when a panel appears, reusing the same aroma-fade-in-up
        // keyframe the auth card itself uses on page load — instant d-none
        // toggling felt abrupt for a two-tab switch this visible.
        function reveal(panel) {
            panel.classList.remove('d-none');
            panel.classList.remove('aroma-animate-in');
            void panel.offsetWidth; // restart the animation on repeated switches
            panel.classList.add('aroma-animate-in');
        }

        passwordTab.addEventListener('change', function () {
            if (this.checked) {
                reveal(passwordPanel);
                otpPanel.classList.add('d-none');
            }
        });
        phoneTab.addEventListener('change', function () {
            if (this.checked) {
                reveal(otpPanel);
                passwordPanel.classList.add('d-none');
            }
        });
    })();
</script>
@endpush
