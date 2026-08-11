@extends('layouts.app')

@section('title', __('account.profile.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

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
                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('account.profile.phone') }}</label>
                            <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" dir="ltr"
                                   class="form-control @error('phone') is-invalid @enderror" placeholder="05XXXXXXXX">
                            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
        </div>
    </div>
</div>
@endsection
