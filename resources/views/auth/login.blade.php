@extends('layouts.auth')

@section('title', __('auth_ui.login.title').' — '.$brand['name'])

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.login.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.login.subtitle') }}</p>

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

    @include('auth.partials.social')

    <p class="text-center mb-0 mt-3">
        {{ __('auth_ui.login.no_account') }}
        <a href="{{ route('register') }}">{{ __('auth_ui.login.register_link') }}</a>
    </p>
@endsection
