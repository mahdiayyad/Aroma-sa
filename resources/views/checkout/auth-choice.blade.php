@extends('layouts.app')

@section('title', __('checkout.auth.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
@php($locale = app()->getLocale())
<div class="container checkout-page my-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @include('checkout.partials.stepper', ['step' => 2])

            <div class="text-center mb-4">
                <h2 class="aroma-section-title">{{ __('checkout.auth.title') }}</h2>
                <p class="text-aroma-muted mb-0">{{ __('checkout.auth.subtitle') }}</p>
            </div>

            <div class="row g-4 mb-4">
                {{-- Sign In --}}
                <div class="col-md-6">
                    <div class="aroma-card h-100">
                        <div class="card-body p-4 text-center d-flex flex-column">
                            <div class="mb-3">
                                <i class="bi bi-box-arrow-in-right fs-1" style="color:var(--aroma-brown)"></i>
                            </div>
                            <h5 class="mb-2" style="color:var(--aroma-brown)">{{ __('checkout.auth.login_title') }}</h5>
                            <p class="text-aroma-muted small flex-grow-1">{{ __('checkout.auth.login_desc') }}</p>
                            <a href="{{ route('checkout.login') }}" class="btn btn-aroma w-100">
                                {{ __('auth_ui.login.submit') }}
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Register --}}
                <div class="col-md-6">
                    <div class="aroma-card h-100">
                        <div class="card-body p-4 text-center d-flex flex-column">
                            <div class="mb-3">
                                <i class="bi bi-person-plus fs-1" style="color:var(--aroma-brown)"></i>
                            </div>
                            <h5 class="mb-2" style="color:var(--aroma-brown)">{{ __('checkout.auth.register_title') }}</h5>
                            <p class="text-aroma-muted small flex-grow-1">{{ __('checkout.auth.register_desc') }}</p>
                            <a href="{{ route('checkout.register') }}" class="btn btn-aroma-outline w-100">
                                {{ __('auth_ui.register.submit') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Benefits reminder --}}
            <div class="aroma-trust p-4 mb-4">
                <div class="row g-3 text-center">
                    <div class="col-sm-4">
                        <i class="bi bi-truck d-block mb-1" style="color:var(--aroma-light-brown)"></i>
                        <small class="text-aroma-muted">{{ __('checkout.auth.benefit_tracking') }}</small>
                    </div>
                    <div class="col-sm-4">
                        <i class="bi bi-clock-history d-block mb-1" style="color:var(--aroma-light-brown)"></i>
                        <small class="text-aroma-muted">{{ __('checkout.auth.benefit_faster') }}</small>
                    </div>
                    <div class="col-sm-4">
                        <i class="bi bi-geo-alt d-block mb-1" style="color:var(--aroma-light-brown)"></i>
                        <small class="text-aroma-muted">{{ __('checkout.auth.benefit_addresses') }}</small>
                    </div>
                </div>
            </div>

            {{-- Continue as Guest --}}
            <div class="text-center">
                <a href="{{ route('checkout.address') }}" class="text-decoration-none" style="color:var(--aroma-ink)">
                    {{ __('checkout.auth.continue_as_guest') }} <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} small"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
