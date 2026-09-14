@extends('layouts.auth')

@section('title', __('auth_ui.register.title').' — '.$brand['name'])

@section('content')
    <h1 class="h3 mb-1">{{ __('auth_ui.register.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('auth_ui.register.subtitle') }}</p>

    <form method="post" action="{{ route('register.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.name') }} <span class="aroma-required">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <p class="form-text mt-n1 mb-2">{{ __('auth_ui.register.contact_hint') }}</p>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.phone') }} <span class="aroma-required">*</span></label>
            <x-phone-input name="phone" />
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.email') }} <span class="aroma-required">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('auth_ui.register.password') }} <span class="aroma-required">*</span></label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('auth_ui.register.confirm') }} <span class="aroma-required">*</span></label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.gender') }}</label>
            @php($gender = old('gender'))
            <select name="gender" class="form-select">
                <option value="" {{ $gender === null || $gender === '' ? 'selected' : '' }}>{{ __('auth_ui.register.gender_placeholder') }}</option>
                <option value="female" {{ $gender === 'female' ? 'selected' : '' }}>{{ __('auth_ui.register.female') }}</option>
                <option value="male" {{ $gender === 'male' ? 'selected' : '' }}>{{ __('auth_ui.register.male') }}</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.referral_code') }}</label>
            <input type="text" name="referral_code" value="{{ old('referral_code', $referralCode ?? '') }}"
                   class="form-control @error('referral_code') is-invalid @enderror"
                   placeholder="{{ __('auth_ui.register.referral_code_placeholder') }}" dir="ltr" autocomplete="off">
            @error('referral_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="form-text mt-1 mb-0">{{ __('auth_ui.register.referral_code_hint') }}</p>
        </div>

        <button type="submit" class="btn btn-aroma w-100 mb-3">{{ __('auth_ui.register.submit') }}</button>
    </form>

    @include('auth.partials.social')

    <p class="text-center mb-0 mt-3">
        {{ __('auth_ui.register.have_account') }}
        <a href="{{ route('login') }}">{{ __('auth_ui.register.login_link') }}</a>
    </p>
@endsection
