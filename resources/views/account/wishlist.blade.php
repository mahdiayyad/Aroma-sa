@extends('layouts.app')

@section('title', __('account.wishlist.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
<div class="container my-4">
    <h1 class="aroma-section-title">{{ __('account.wishlist.title') }}</h1>
    <div class="row g-4">
        <div class="col-lg-3">
            @include('account.partials.sidebar', ['active' => 'wishlist'])
        </div>
        <div class="col-lg-9">
            @if ($products->isEmpty())
                <div class="aroma-trust p-5 text-center text-aroma-muted">{{ __('account.wishlist.empty') }}</div>
            @else
                <div class="row g-4">
                    @foreach ($products as $product)
                        <div class="col-6 col-md-4">
                            @include('catalog.partials.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
