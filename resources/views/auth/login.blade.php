@extends('layouts.auth')

@section('title', __('auth_ui.login.title').' — '.$brand['name'])

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/otp.css') }}">
@endpush

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.login.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.login.subtitle') }}</p>

    {{-- Two ways in. Both end with a one-time code: a text to the mobile number,
         or — after the password checks out — an email to the account's address. --}}
    @include('auth.partials.method-tabs', ['phonePaneId' => 'phoneLoginPane', 'emailPaneId' => 'emailLoginPane'])

    <div id="phoneLoginPane" class="{{ request()->query('method') === 'email' ? 'd-none' : '' }}">
        @include('auth.partials.otp-panel')
    </div>

    <div id="emailLoginPane" class="{{ request()->query('method') === 'email' ? '' : 'd-none' }}">
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
@endpush
