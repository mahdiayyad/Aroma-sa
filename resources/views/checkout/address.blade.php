@extends('layouts.app')

@section('title', __('checkout.address').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
@php($locale = app()->getLocale())
<div class="container checkout-page my-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @include('checkout.partials.stepper', ['step' => 3])

            <h2 class="aroma-section-title">{{ __('checkout.address') }}</h2>

            <form method="POST" action="{{ route('checkout.address.store') }}" class="needs-validation">
                @csrf

                <div class="aroma-card mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title">{{ __('checkout.address') }}</h5>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('checkout.recipient_name') }}</label>
                                <input type="text" name="billing_address[recipient_name]" class="form-control @error('billing_address.recipient_name') is-invalid @enderror"
                                       value="{{ old('billing_address.recipient_name', optional(auth()->user())->name) }}" required>
                                @error('billing_address.recipient_name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('checkout.phone') }}</label>
                                <input type="tel" name="billing_address[phone]" class="form-control @error('billing_address.phone') is-invalid @enderror"
                                       value="{{ old('billing_address.phone', optional(auth()->user())->phone) }}" required>
                                @error('billing_address.phone')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    {{ __('checkout.email') }}
                                    @guest <span class="text-danger">*</span> @endguest
                                </label>
                                <input type="email" name="billing_address[email]" class="form-control @error('billing_address.email') is-invalid @enderror"
                                       value="{{ old('billing_address.email', optional(auth()->user())->email) }}" @guest required @endguest>
                                @error('billing_address.email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">{{ __('checkout.street_address') }}</label>
                                <input type="text" name="billing_address[street_address]" class="form-control @error('billing_address.street_address') is-invalid @enderror"
                                       value="{{ old('billing_address.street_address') }}" required>
                                @error('billing_address.street_address')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">{{ __('checkout.city') }}</label>
                                <input type="text" name="billing_address[city]" class="form-control @error('billing_address.city') is-invalid @enderror"
                                       value="{{ old('billing_address.city') }}" required>
                                @error('billing_address.city')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">{{ __('checkout.region') }}</label>
                                <input type="text" name="billing_address[region]" class="form-control @error('billing_address.region') is-invalid @enderror"
                                       value="{{ old('billing_address.region') }}" required>
                                @error('billing_address.region')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">{{ __('checkout.postal_code') }}</label>
                                <input type="text" name="billing_address[postal_code]" class="form-control"
                                       value="{{ old('billing_address.postal_code') }}">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">{{ __('checkout.customer_notes') }}</label>
                                <textarea name="customer_notes" class="form-control" rows="3" placeholder="...">{{ old('customer_notes') }}</textarea>
                            </div>
                        </div>

                        {{-- Carries the pinned map location through to the order, when a
                             saved address (set via the account map picker) is selected
                             below. Empty when the shopper types a fresh address here. --}}
                        <input type="hidden" name="billing_address[latitude]" id="billingLatitude" value="{{ old('billing_address.latitude') }}">
                        <input type="hidden" name="billing_address[longitude]" id="billingLongitude" value="{{ old('billing_address.longitude') }}">
                    </div>
                </div>

                {{-- Saved Addresses (for authenticated users) --}}
                @if(auth()->check() && $addresses->count() > 0)
                    <div class="aroma-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('account.saved_addresses') }}</h5>

                            <div class="list-group list-group-flush">
                                @foreach($addresses as $address)
                                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-start gap-3 border-0 p-3"
                                            onclick="loadAddress(this)" data-recipient="{{ $address->recipient_name }}"
                                            data-phone="{{ $address->phone }}" data-street="{{ $address->street_address }}"
                                            data-city="{{ $address->city }}" data-region="{{ $address->region }}"
                                            data-postal="{{ $address->postal_code }}"
                                            data-lat="{{ $address->latitude }}" data-lng="{{ $address->longitude }}">
                                        <div>
                                            <strong>{{ $address->label ?? 'Address' }}</strong>
                                            <div class="small text-aroma-muted">{{ $address->street_address }}, {{ $address->city }}</div>
                                            @if ($address->hasCoordinates())
                                                <div class="small text-aroma-brown"><i class="bi bi-geo-alt-fill me-1"></i>{{ __('account.saved_addresses_pinned') }}</div>
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div class="d-flex gap-3 justify-content-between aroma-actions-stack">
                    <a href="{{ route('checkout.review') }}" class="btn btn-aroma-outline">
                        <i class="bi {{ $locale === 'ar' ? 'bi-chevron-right' : 'bi-chevron-left' }} me-2"></i>{{ __('checkout.buttons.back') }}
                    </a>
                    <button type="submit" class="btn btn-aroma btn-lg">
                        {{ __('checkout.buttons.continue') }} <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function loadAddress(btn) {
    document.querySelector('[name="billing_address[recipient_name]"]').value = btn.dataset.recipient;
    document.querySelector('[name="billing_address[phone]"]').value = btn.dataset.phone;
    document.querySelector('[name="billing_address[street_address]"]').value = btn.dataset.street;
    document.querySelector('[name="billing_address[city]"]').value = btn.dataset.city;
    document.querySelector('[name="billing_address[region]"]').value = btn.dataset.region;
    document.querySelector('[name="billing_address[postal_code]"]').value = btn.dataset.postal;
    document.getElementById('billingLatitude').value = btn.dataset.lat || '';
    document.getElementById('billingLongitude').value = btn.dataset.lng || '';
}
</script>
@endpush
@endsection
