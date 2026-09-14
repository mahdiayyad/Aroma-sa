@extends('layouts.auth')

@section('title', __('otp.complete_profile.title').' — '.$brand['name'])

@section('content')
    <h1 class="h3 mb-1">{{ __('otp.complete_profile.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('otp.complete_profile.subtitle') }}</p>

    <form method="post" action="{{ route('otp.complete-profile.store') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">{{ __('otp.phone_label') }}</label>
            <input type="text" value="{{ $phone }}" class="form-control" dir="ltr" disabled>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('otp.complete_profile.name') }} <span class="aroma-required">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('otp.complete_profile.email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('otp.complete_profile.gender') }}</label>
            @php($gender = old('gender'))
            <select name="gender" class="form-select">
                <option value="" {{ $gender === null || $gender === '' ? 'selected' : '' }}>{{ __('otp.complete_profile.gender_placeholder') }}</option>
                <option value="female" {{ $gender === 'female' ? 'selected' : '' }}>{{ __('otp.complete_profile.female') }}</option>
                <option value="male" {{ $gender === 'male' ? 'selected' : '' }}>{{ __('otp.complete_profile.male') }}</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('auth_ui.register.referral_code') }}</label>
            <input type="text" name="referral_code" value="{{ old('referral_code') }}"
                   class="form-control @error('referral_code') is-invalid @enderror"
                   placeholder="{{ __('auth_ui.register.referral_code_placeholder') }}" dir="ltr" autocomplete="off">
            @error('referral_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-aroma w-100 mb-3">{{ __('otp.complete_profile.submit') }}</button>
    </form>
@endsection
