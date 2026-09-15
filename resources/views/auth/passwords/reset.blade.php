@extends('layouts.auth')

@section('title', __('auth_ui.password.reset_title').' — '.$brand['name'])

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.password.reset_title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.password.reset_subtitle') }}</p>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.password.email') }}</label>
            <input type="email" name="email" value="{{ old('email', $email) }}"
                   class="form-control @error('email') is-invalid @enderror" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.password.new') }}</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.password.confirm') }}</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-aroma w-100 mb-3">{{ __('auth_ui.password.reset_submit') }}</button>
    </form>

    <p class="text-center mb-0"><a href="{{ route('login') }}">{{ __('auth_ui.password.back_to_login') }}</a></p>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::versioned('js/password-confirm-match.js') }}"></script>
@endpush
