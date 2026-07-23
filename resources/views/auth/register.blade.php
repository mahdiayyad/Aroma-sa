@extends('layouts.auth')

@section('title', __('auth_ui.register.title').' — '.$brand['name'])

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.register.title') }}</h1>
    <p class="text-muted mb-4">{{ __('auth_ui.register.subtitle') }}</p>

    <form method="post" action="{{ route('register.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.phone') }}</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" dir="ltr"
                   class="form-control @error('phone') is-invalid @enderror" placeholder="05XXXXXXXX">
            <div class="form-text">{{ __('auth_ui.register.phone_hint') }}</div>
            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('auth_ui.register.password') }}</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('auth_ui.register.confirm') }}</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.gender') }}</label>
            <select name="gender" class="form-select">
                <option value="female" @selected(old('gender') === 'female')>{{ __('auth_ui.register.female') }}</option>
                <option value="male" @selected(old('gender') === 'male')>{{ __('auth_ui.register.male') }}</option>
                <option value="unspecified" @selected(old('gender') === 'unspecified')>{{ __('auth_ui.register.unspecified') }}</option>
            </select>
        </div>

        <button type="submit" class="btn btn-aroma w-100 mb-3">{{ __('auth_ui.register.submit') }}</button>
    </form>

    @include('auth.partials.social')

    <p class="text-center mb-0 mt-3">
        {{ __('auth_ui.register.have_account') }}
        <a href="{{ route('login') }}">{{ __('auth_ui.register.login_link') }}</a>
    </p>
@endsection
