@extends('layouts.auth')

@section('title', __('auth_ui.password.forgot_title').' — '.$brand['name'])

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.password.forgot_title') }}</h1>
    <p class="text-muted mb-4">{{ __('auth_ui.password.forgot_subtitle') }}</p>

    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.password.email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-aroma w-100 mb-3">{{ __('auth_ui.password.send') }}</button>
    </form>

    <p class="text-center mb-0"><a href="{{ route('login') }}">{{ __('auth_ui.password.back_to_login') }}</a></p>
@endsection
