@extends('layouts.auth')

@section('title', __('auth_ui.register.title').' — '.$brand['name'])

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/otp.css') }}">
@endpush

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.register.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.register.subtitle') }}</p>

    {{-- Same verified-code flow as sign-in: a number with no account gets one
         right after its code checks out (then we only ask for a name). --}}
    @include('auth.partials.otp-panel')

    <p class="form-text text-center mt-0 mb-2">{{ __('auth_ui.register.email_hint') }}</p>

    @include('auth.partials.social')

    <p class="text-center mb-0 mt-4">
        {{ __('auth_ui.register.have_account') }}
        <a href="{{ route('login') }}" class="fw-semibold text-decoration-underline">{{ __('auth_ui.register.login_link') }}</a>
    </p>
@endsection

@push('scripts')
<script src="{{ \App\Support\Assets::versioned('js/otp-auth.js') }}"></script>
@endpush
