@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';

    // Terms is reachable from the sitewide footer on every checkout step, not
    // just from its own payment-page modal — a reader who followed it from
    // mid-checkout needs a way back to exactly that step, not just "Home".
    // url()->previous() resolves from the Referer header Laravel/the browser
    // already sends, so this works for any referring page, not only checkout.
    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();
@endphp

@section('title', ($isAr ? 'الشروط والأحكام' : 'Terms & Conditions').' — '.$brand['name'])

@section('content')
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @if ($showBack)
                <x-back-link :href="$backUrl" class="mb-4" />
            @endif

            <div class="text-center mb-5">
                <span class="aroma-eyebrow d-block mb-2">{{ $brand['name'] }}</span>
                <h1 class="aroma-section-title d-inline-block">
                    {{ $isAr ? 'الشروط والأحكام' : 'Terms & Conditions' }}
                </h1>
                <p class="text-aroma-muted mt-3 mb-0">
                    {{ $isAr ? 'آخر تحديث' : 'Last updated' }}: {{ now()->translatedFormat('j F Y') }}
                </p>
            </div>

            <div class="aroma-card">
                <div class="card-body p-4 p-lg-5">
                    @include('pages.partials.terms-content')
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="{{ route('home', $locale) }}" class="btn btn-aroma-outline">
                    <i class="bi bi-arrow-{{ $isAr ? 'right' : 'left' }} me-2"></i>{{ __('checkout.continue_shopping') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
