@extends('layouts.app')

@section('title', __('checkout.payment').' — '.$brand['name'])

@section('content')
@php($locale = app()->getLocale())
<div class="container my-5">
    <div class="row g-4">
        {{-- Order Summary --}}
        <div class="col-lg-4">
            <div class="aroma-card sticky-top" style="top:20px">
                <div class="card-body">
                    <h5 class="card-title mb-3" style="color:var(--aroma-brown)">{{ __('checkout.review') }}</h5>

                    {{-- Items List --}}
                    <div class="mb-3 pb-3 border-bottom">
                        @foreach(session('cart', []) as $item)
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2 small">
                                <div>
                                    <strong>{{ $item['name'][app()->getLocale()] ?? $item['name']['en'] }}</strong>
                                    <div class="text-aroma-muted small">x{{ $item['qty'] }}</div>
                                </div>
                                <div class="text-end">@price($item['unit_price'] * $item['qty'])</div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Totals --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-aroma-muted">{{ __('checkout.subtotal') }}</span>
                            <span>@price($totals['subtotal'])</span>
                        </div>

                        @if($totals['discount_amount'] > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.discount') }}</span>
                                <span class="text-success">-@price($totals['discount_amount'])</span>
                            </div>
                        @endif

                        @if($totals['shipping_cost'] > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.shipping') }}</span>
                                <span>@price($totals['shipping_cost'])</span>
                            </div>
                        @endif

                        @if($totals['tax_amount'] > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-aroma-muted">{{ __('checkout.tax') }}</span>
                                <span>@price($totals['tax_amount'])</span>
                            </div>
                        @endif
                    </div>

                    <div class="p-3 rounded" style="background:var(--aroma-skin)">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong style="color:var(--aroma-brown);font-size:1.1rem">{{ __('checkout.total') }}</strong>
                            <strong style="color:var(--aroma-brown);font-size:1.3rem">@price($totals['total_amount'])</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment Form --}}
        <div class="col-lg-8">
            <div class="row justify-content-center mb-5">
                <div class="col-8">
                    {{-- Progress --}}
                    <div class="d-flex justify-content-between text-center text-aroma-muted small mb-3">
                        <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('checkout.steps.review') }}</span>
                        <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('checkout.steps.account') }}</span>
                        <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('checkout.steps.address') }}</span>
                        <span class="fw-bold" style="color:var(--aroma-brown)"><i class="bi bi-credit-card"></i> {{ __('checkout.steps.payment') }}</span>
                    </div>
                    <div class="progress" style="height:4px">
                        <div class="progress-bar" style="width:100%;background:var(--aroma-brown)"></div>
                    </div>
                </div>
            </div>

            <h2 class="aroma-section-title mb-4">{{ __('checkout.payment') }}</h2>

            <form method="POST" action="{{ route('checkout.payment.store') }}" id="paymentForm" class="needs-validation">
                @csrf

                {{-- Payment Gateway Selection --}}
                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4" style="color:var(--aroma-brown)">{{ __('checkout.payment_gateway') }}</h5>

                        <div class="row g-3">
                            @foreach(config('aroma.payments.methods', []) as $method)
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="gateway" id="gateway-moyasar"
                                           value="moyasar" {{ old('gateway') === 'moyasar' ? 'checked' : '' }} required>
                                    <label class="btn btn-aroma-outline w-100" for="gateway-moyasar"
                                           style="border-color:var(--aroma-brown);color:var(--aroma-brown)">
                                        <i class="bi bi-credit-card me-2"></i>{{ __('checkout.payment_methods.'.strtolower($method)) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        @error('gateway')
                            <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Payment Methods (placeholder for gateway SDK) --}}
                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4" style="color:var(--aroma-brown)">{{ __('checkout.payment_method') }}</h5>

                        <div class="aroma-alert aroma-alert-info" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            {{ __('checkout.placeholder', ['gateway' => 'Moyasar']) }}
                        </div>

                        <input type="hidden" name="method" value="card">

                        {{-- This will be replaced with actual payment SDK form --}}
                        <div id="payment-form" class="p-4 border rounded" style="background:var(--aroma-white);border-color:var(--aroma-light-brown)!important">
                            <p class="text-aroma-muted text-center mb-0">
                                {{ __('checkout.payment_gateway_placeholder') }}
                            </p>
                        </div>

                        @error('method')
                            <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Shipping Method --}}
                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4" style="color:var(--aroma-brown)">{{ __('checkout.shipping_method') }}</h5>

                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shipping_method" id="shipping-standard"
                                       value="standard" {{ old('shipping_method', 'standard') === 'standard' ? 'checked' : '' }}>
                                <label class="form-check-label" for="shipping-standard">
                                    <strong>{{ __('checkout.shipping_methods.standard') }}</strong>
                                    <div class="small text-aroma-muted">{{ __('checkout.shipping_days.standard') }}</div>
                                </label>
                            </div>
                        </div>

                        @error('shipping_method')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Terms & Conditions --}}
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        {{ __('checkout.agree_terms', ['link' => route('terms')]) }}
                    </label>
                </div>

                {{-- Action Buttons --}}
                <div class="d-flex gap-3 justify-content-between">
                    <a href="{{ route('checkout.address') }}" class="btn btn-aroma-outline">
                        <i class="bi {{ $locale === 'ar' ? 'bi-chevron-right' : 'bi-chevron-left' }} me-2"></i>{{ __('checkout.buttons.back') }}
                    </a>
                    <button type="submit" class="btn btn-aroma btn-lg">
                        {{ __('checkout.buttons.place_order') }} <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // TODO: Integrate Moyasar payment SDK
    // This is a placeholder that will be replaced with actual payment gateway integration
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        // In production, call payment gateway API before submitting
        // e.preventDefault();
        // moyasar.tokenize({...})
    });
</script>
@endpush
@endsection
