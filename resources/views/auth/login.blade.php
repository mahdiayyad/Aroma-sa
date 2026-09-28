@extends('layouts.auth')

@section('title', __('auth_ui.login.title').' — '.$brand['name'])

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/otp.css') }}">
@endpush

@section('content')
    @php($emailFirst = request()->query('method') === 'email')

    <h1 class="h3 mb-1">{{ __('auth_ui.login.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.login.subtitle') }}</p>

    {{-- Two ways in. Both end with a one-time code: a text to the mobile number,
         or — after the password checks out — an email to the account's address. --}}
    <div class="aroma-segmented aroma-segmented-block" role="tablist">
        <input type="radio" class="btn-check" name="authMethod" id="authMethodPhone" {{ $emailFirst ? '' : 'checked' }}>
        <label class="aroma-segmented-option" for="authMethodPhone">{{ __('email_auth.tab_phone') }}</label>

        <input type="radio" class="btn-check" name="authMethod" id="authMethodEmail" {{ $emailFirst ? 'checked' : '' }}>
        <label class="aroma-segmented-option" for="authMethodEmail">{{ __('email_auth.tab_email') }}</label>
    </div>

    <div id="phoneLoginPane" class="{{ $emailFirst ? 'd-none' : '' }}">
        @include('auth.partials.otp-panel')
    </div>

    <div id="emailLoginPane" class="{{ $emailFirst ? '' : 'd-none' }}">
        @include('auth.partials.email-panel')
    </div>

    @include('auth.partials.social')

    <p class="text-center mb-0 mt-4">
        {{ __('auth_ui.login.no_account') }}
        <a href="{{ route('register', array_filter(['ref' => session('referral_code_prefill')])) }}" class="fw-semibold text-decoration-underline">{{ __('auth_ui.login.register_link') }}</a>
    </p>
@endsection

@push('scripts')
<script src="{{ \App\Support\Assets::versioned('js/otp-auth.js') }}"></script>
<script>
    (function () {
        var phoneTab = document.getElementById('authMethodPhone');
        var emailTab = document.getElementById('authMethodEmail');
        var phonePane = document.getElementById('phoneLoginPane');
        var emailPane = document.getElementById('emailLoginPane');
        if (!phoneTab || !emailTab) { return; }

        // Soft fade-in when a pane appears, reusing the keyframe the auth card
        // itself uses on page load.
        function reveal(pane) {
            pane.classList.remove('d-none');
            pane.classList.remove('aroma-animate-in');
            void pane.offsetWidth;
            pane.classList.add('aroma-animate-in');
        }

        phoneTab.addEventListener('change', function () {
            if (this.checked) { reveal(phonePane); emailPane.classList.add('d-none'); }
        });
        emailTab.addEventListener('change', function () {
            if (this.checked) { reveal(emailPane); phonePane.classList.add('d-none'); }
        });
    })();
</script>
@endpush
