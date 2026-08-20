@extends('layouts.app')

@php($editing = $address->exists)
@section('title', ($editing ? __('account.addresses.edit') : __('account.addresses.new')).' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
<div class="container my-4">
    <h1 class="aroma-section-title">{{ $editing ? __('account.addresses.edit') : __('account.addresses.new') }}</h1>
    <div class="row g-4">
        <div class="col-lg-3">
            @include('account.partials.sidebar', ['active' => 'addresses'])
        </div>

        <div class="col-lg-9">
            <div class="aroma-trust p-4">
                <form method="post" action="{{ $editing ? route('account.addresses.update', $address) : route('account.addresses.store') }}">
                    @csrf
                    @if ($editing) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('account.addresses.label') }}</label>
                            <input type="text" name="label" value="{{ old('label', $address->label) }}"
                                   class="form-control @error('label') is-invalid @enderror" placeholder="{{ __('account.addresses.label_placeholder') }}">
                            @error('label')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('checkout.recipient_name') }}</label>
                            <input type="text" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name) }}"
                                   class="form-control @error('recipient_name') is-invalid @enderror" required>
                            @error('recipient_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('checkout.phone') }}</label>
                            <input type="tel" name="phone" dir="ltr" value="{{ old('phone', $address->phone) }}"
                                   class="form-control @error('phone') is-invalid @enderror" placeholder="05XXXXXXXX" required>
                            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">{{ __('location.label') }}</label>
                            <div class="aroma-location-field">
                                <input type="text" name="location_code"
                                       class="form-control js-location-code @error('location_code') is-invalid @enderror"
                                       value="{{ old('location_code', $address->location_code) }}" maxlength="8"
                                       placeholder="{{ __('location.placeholder') }}"
                                       data-lang-loading="{{ __('location.preview.loading') }}"
                                       data-lang-not-found="{{ __('location.errors.not_found') }}"
                                       data-lang-invalid="{{ __('location.errors.invalid_format') }}"
                                       data-lang-failed="{{ __('location.errors.lookup_failed') }}"
                                       required>
                                <div class="aroma-location-preview" hidden></div>
                            </div>
                            <div class="form-text">{{ __('location.hint') }}</div>
                            @error('location_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="isDefault" value="1"
                                       {{ old('is_default', $address->is_default) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isDefault">{{ __('account.addresses.set_default') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 justify-content-between mt-4 aroma-actions-stack">
                        <a href="{{ route('account.addresses.index') }}" class="btn btn-aroma-outline">{{ __('checkout.buttons.back') }}</a>
                        <button type="submit" class="btn btn-aroma">{{ __('account.addresses.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ \App\Support\Assets::versioned('js/location-lookup.js') }}"></script>
@endpush
@endsection
