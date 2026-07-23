@extends('layouts.app')

@section('title', __('checkout.auth.title').' — '.$brand['name'])

@section('content')
@php($locale = app()->getLocale())
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            {{-- Progress Indicator --}}
            <div class="mb-5">
                <div class="d-flex justify-content-between text-center text-aroma-muted small mb-3">
                    <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('checkout.steps.review') }}</span>
                    <span class="fw-bold" style="color:var(--aroma-brown)"><i class="bi bi-person"></i> {{ __('checkout.steps.account') }}</span>
                    <span>{{ __('checkout.steps.address') }}</span>
                    <span>{{ __('checkout.steps.payment') }}</span>
                </div>
                <div class="progress" style="height:4px">
                    <div class="progress-bar" style="width:50%;background:var(--aroma-brown)"></div>
                </div>
            </div>

            <div class="text-center mb-5">
                <h2 class="aroma-section-title">{{ __('checkout.auth.title') }}</h2>
                <p class="text-aroma-muted">{{ __('checkout.auth.subtitle') }}</p>
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
