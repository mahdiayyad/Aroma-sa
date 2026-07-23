@extends('layouts.app')

@section('title', __('account.title').' — '.$brand['name'])

@section('content')
<div class="container my-4">
    <h1 class="aroma-section-title">{{ __('account.welcome', ['name' => $user->name]) }}</h1>
    <div class="row g-4">
        <div class="col-lg-3">
            @include('account.partials.sidebar', ['active' => 'dashboard'])
        </div>
        <div class="col-lg-9">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="aroma-trust p-4 text-center">
                        <i class="bi bi-gem fs-2" style="color:var(--aroma-brown)"></i>
                        <div class="fs-3 fw-bold">{{ $user->loyalty_points }}</div>
                        <div class="text-muted small">{{ __('account.stats.points') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="aroma-trust p-4 text-center">
                        <i class="bi bi-receipt fs-2" style="color:var(--aroma-brown)"></i>
                        <div class="fs-3 fw-bold">0</div>
                        <div class="text-muted small">{{ __('account.stats.orders') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('wishlist.index') }}" class="text-decoration-none">
                        <div class="aroma-trust p-4 text-center">
                            <i class="bi bi-heart fs-2" style="color:var(--aroma-brown)"></i>
                            <div class="fs-3 fw-bold">{{ $wishlistCount }}</div>
                            <div class="text-muted small">{{ __('account.stats.wishlist') }}</div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="aroma-trust p-4 mt-4">
                <h5 class="mb-3">{{ __('account.recent_orders') }}</h5>
                <p class="text-muted mb-0">{{ __('account.no_orders') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
