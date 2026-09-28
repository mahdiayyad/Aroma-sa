@extends('layouts.app')

@section('title', __('account.profile.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/otp.css') }}">
@endpush

@section('content')
<div class="container my-4">
    <h1 class="aroma-section-title">{{ __('account.profile.title') }}</h1>
    <div class="row g-4">
        <div class="col-lg-3">
            @include('account.partials.sidebar', ['active' => 'profile'])
        </div>
        <div class="col-lg-9">
            <div class="aroma-trust p-4">
                <form method="post" action="{{ route('account.profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('account.profile.name') }}</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('account.profile.email') }}</label>
                            @if ($user->password)
                                {{-- A sign-in credential now (login codes go here): not a free-text field. --}}
                                <input type="email" value="{{ $user->email }}" class="form-control" dir="ltr" readonly disabled>
                                <div class="form-text">{{ __('email_auth.profile.email_locked_hint') }}</div>
                            @else
                                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                       class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('account.profile.phone') }}</label>
                            {{-- The mobile number is the sign-in credential, so it is never a
                                 free-text field: changing it means proving the new number. --}}
                            <div class="input-group">
                                <input type="text" value="{{ $user->phone }}" class="form-control" dir="ltr" readonly disabled>
                                <button type="button" class="btn btn-aroma-outline" data-bs-toggle="collapse"
                                        data-bs-target="#phoneChangeCard" aria-expanded="false"
                                        aria-controls="phoneChangeCard">{{ __('otp.change_phone.action') }}</button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('account.profile.dob') }}</label>
                            <input type="date" name="dob" value="{{ old('dob', optional($user->dob)->format('Y-m-d')) }}"
                                   class="form-control @error('dob') is-invalid @enderror">
                            @error('dob')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('account.profile.gender') }}</label>
                            @php($gender = old('gender', $user->gender))
                            <select name="gender" class="form-select">
                                <option value="" {{ in_array($gender, ['female', 'male'], true) ? '' : 'selected' }}>{{ __('auth_ui.register.gender_placeholder') }}</option>
                                <option value="female" {{ $gender === 'female' ? 'selected' : '' }}>{{ __('auth_ui.register.female') }}</option>
                                <option value="male" {{ $gender === 'male' ? 'selected' : '' }}>{{ __('auth_ui.register.male') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('account.profile.language') }}</label>
                            @php($userLocale = old('locale', $user->locale ?? app()->getLocale()))
                            <select name="locale" class="form-select">
                                <option value="ar" {{ $userLocale === 'ar' ? 'selected' : '' }}>العربية</option>
                                <option value="en" {{ $userLocale === 'en' ? 'selected' : '' }}>English</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-aroma">{{ __('account.profile.save') }}</button>
                </form>
            </div>

            <div class="aroma-trust p-4 mt-4" id="emailSignInCard">
                <h2 class="h5 mb-1">{{ __('email_auth.profile.title') }}</h2>
                @if ($user->password)
                    <p class="text-aroma-muted mb-3">
                        {{ __('email_auth.profile.signin_email') }}: <bdi dir="ltr">{{ $user->email }}</bdi>
                    </p>
                    <h3 class="h6 mb-3">{{ __('email_auth.profile.change_title') }}</h3>
                    <form method="post" action="{{ route('account.security.password') }}">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('email_auth.profile.current_password') }}</label>
                                <input type="password" name="current_password" autocomplete="current-password"
                                       class="form-control @error('current_password') is-invalid @enderror" required>
                                @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('email_auth.profile.new_password') }}</label>
                                <input type="password" name="password" autocomplete="new-password"
                                       class="form-control @error('password') is-invalid @enderror" required>
                                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('email_auth.profile.confirm') }}</label>
                                <input type="password" name="password_confirmation" autocomplete="new-password" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-aroma">{{ __('email_auth.profile.save_password') }}</button>
                    </form>
                @else
                    <p class="text-aroma-muted mb-3">{{ __('email_auth.profile.intro_add') }}</p>
                    @include('auth.partials.email-add-panel')
                @endif
            </div>

            <div class="collapse mt-4" id="phoneChangeCard">
                <div class="aroma-trust p-4">
                    <h2 class="h5 mb-1">{{ __('otp.change_phone.title') }}</h2>
                    <p class="text-aroma-muted mb-3">{{ __('otp.change_phone.subtitle') }}</p>
                    @include('auth.partials.otp-panel', [
                        'panelId' => 'otpPhoneChangePanel',
                        'sendUrl' => route('account.phone.send'),
                        'verifyUrl' => route('account.phone.verify'),
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ \App\Support\Assets::versioned('js/otp-auth.js') }}"></script>
@endpush
