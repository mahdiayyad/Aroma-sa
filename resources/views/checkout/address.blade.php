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
                                <x-phone-input name="billing_address[phone]" :value="optional(auth()->user())->phone" required />
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
                                <label class="form-label fw-semibold">{{ __('location.label') }}</label>
                                <x-location-picker field-prefix="billing_address" dom-id="billing" />
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">{{ __('checkout.customer_notes') }}</label>
                                <textarea name="customer_notes" class="form-control @error('customer_notes') is-invalid @enderror" rows="3" placeholder="...">{{ old('customer_notes') }}</textarea>
                                @error('customer_notes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
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
                                            data-phone="{{ $address->phone }}"
                                            data-location-code="{{ $address->location_code }}"
                                            data-lat="{{ $address->latitude }}" data-lng="{{ $address->longitude }}">
                                        <div>
                                            <strong>{{ $address->label ?? 'Address' }}</strong>
                                            <div class="small text-aroma-muted">
                                                {{ $address->formatted_address ?: ($address->street_address ?: ($address->hasCoordinates() ? __('location.map.pinned_label') : '')) }}
                                            </div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div class="d-flex gap-3 justify-content-between aroma-actions-stack">
                    <x-back-link :href="$backRoute" />
                    <button type="submit" class="btn btn-aroma btn-lg">
                        {{ __('checkout.buttons.continue') }} <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ \App\Support\Assets::versioned('js/location-lookup.js') }}"></script>
<script>
function loadAddress(btn) {
    document.querySelector('[name="billing_address[recipient_name]"]').value = btn.dataset.recipient;

    // Parse the saved address's full stored number and let intl-tel-input
    // pick the matching flag/country itself, rather than assuming a format.
    var phoneInput = document.querySelector('[name="billing_address[phone]"]');
    if (phoneInput._iti && btn.dataset.phone) {
        phoneInput._iti.setNumber(btn.dataset.phone);
    } else {
        phoneInput.value = btn.dataset.phone || '';
    }

    if (btn.dataset.locationCode) {
        var codeInput = document.querySelector('.js-location-code');
        codeInput.value = btn.dataset.locationCode;
        codeInput.dispatchEvent(new Event('aroma:location-code-set'));
    }
}
</script>
@endpush
@endsection
