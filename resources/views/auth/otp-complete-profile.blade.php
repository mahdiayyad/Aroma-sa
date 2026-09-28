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

        {{-- Referral code: optional, so it stays out of the way unless the
             shopper came from an invite link (then it's already filled in
             and the field is open) or asks for it. --}}
        @php($referral = old('referral_code', $referralPrefill ?? ''))
        @if ($referral === '' && ! $errors->has('referral_code'))
            <p class="mb-3">
                <a href="#referralField" class="small" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="referralField">
                    {{ __('auth_ui.register.referral_toggle') }}
                </a>
            </p>
        @endif
        <div class="mb-3 collapse {{ $referral !== '' || $errors->has('referral_code') ? 'show' : '' }}" id="referralField">
            <label class="form-label">{{ __('auth_ui.register.referral_code') }}</label>
            <input type="text" name="referral_code" value="{{ $referral }}"
                   class="form-control @error('referral_code') is-invalid @enderror"
                   placeholder="{{ __('auth_ui.register.referral_code_placeholder') }}" dir="ltr" autocomplete="off">
            @error('referral_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="form-text mt-1 mb-0">{{ __('auth_ui.register.referral_code_hint') }}</p>
        </div>

        <button type="submit" class="btn btn-aroma w-100 mb-3">{{ __('otp.complete_profile.submit') }}</button>
    </form>
@endsection
