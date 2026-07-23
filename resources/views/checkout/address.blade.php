@extends('layouts.app')

@section('title', __('checkout.address').' — '.$brand['name'])

@section('content')
@php($locale = app()->getLocale())
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            {{-- Progress Indicator --}}
            <div class="mb-5">
                <div class="d-flex justify-content-between text-center text-aroma-muted small mb-3">
                    <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('checkout.steps.review') }}</span>
                    <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('checkout.steps.account') }}</span>
                    <span class="fw-bold" style="color:var(--aroma-brown)"><i class="bi bi-geo-alt"></i> {{ __('checkout.steps.address') }}</span>
                    <span>{{ __('checkout.steps.payment') }}</span>
                </div>
                <div class="progress" style="height:4px">
                    <div class="progress-bar" style="width:75%;background:var(--aroma-brown)"></div>
                </div>
            </div>

            <h2 class="aroma-section-title mb-4">{{ __('checkout.address') }}</h2>

            <form method="POST" action="{{ route('checkout.address.store') }}" class="needs-validation">
                @csrf

                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3" style="color:var(--aroma-brown)">{{ __('checkout.address') }}</h5>

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
                    </div>
                </div>

                {{-- Saved Addresses (for authenticated users) --}}
                @if(auth()->check() && $addresses->count() > 0)
                    <div class="aroma-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3" style="color:var(--aroma-brown)">{{ __('account.saved_addresses') }}</h5>

                            <div class="list-group list-group-flush">
                                @foreach($addresses as $address)
                                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-start gap-3 border-0 p-3"
                                            onclick="loadAddress(this)" data-recipient="{{ $address->recipient_name }}"
                                            data-phone="{{ $address->phone }}" data-street="{{ $address->street_address }}"
                                            data-city="{{ $address->city }}" data-region="{{ $address->region }}"
                                            data-postal="{{ $address->postal_code }}">
                                        <div>
                                            <strong>{{ $address->label ?? 'Address' }}</strong>
                                            <div class="small text-aroma-muted">{{ $address->street_address }}, {{ $address->city }}</div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div class="d-flex gap-3 justify-content-between">
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
}
</script>
@endpush
@endsection
