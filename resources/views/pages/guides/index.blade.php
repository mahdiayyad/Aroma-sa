@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';
    $waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp'));

    // Reachable from the sitewide footer at any point in checkout — same
    // referrer-guarded back link as terms.blade.php / privacy-policy.blade.php.
    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();

    // Sizing/Fit/Care are abaya-garment guides — hidden while the storefront
    // is perfume-led; restore alongside AbayaCatalogSeeder / PerfumeCatalogSeeder.
    // Returns stays: it's a generic policy page linked from cart/checkout
    // regardless of product line.
    $cards = $isAr ? [
        // ['bi-rulers', 'guides.sizing', 'دليل المقاسات', 'قيسي نفسك بثقة واعرفي المقاس الأنسب لكل قطعة.'],
        // ['bi-gem', 'guides.fit', 'اختاري قصتك', 'اكتشفي القصات والتصاميم التي تناسب حركتك اليومية ومناسباتك.'],
        // ['bi-droplet', 'guides.care', 'العناية والغسيل', 'حافظي على جمال كل قطعة غسلة بعد غسلة.'],
        ['bi-arrow-repeat', 'guides.returns', 'الاستبدال والإرجاع', 'شرح واضح وصادق لكيفية عمل الإرجاع والاستبدال في أروما.'],
    ] : [
        // ['bi-rulers', 'guides.sizing', 'Size Guide', 'Measure yourself with confidence and find the size that fits.'],
        // ['bi-gem', 'guides.fit', 'Find Your Fit', 'Discover the cuts and silhouettes that match how you move and where you\'re headed.'],
        // ['bi-droplet', 'guides.care', 'Care & Washing', 'Keep every piece looking beautiful, wash after wash.'],
        ['bi-arrow-repeat', 'guides.returns', 'Exchange & Returns', 'A clear, honest walk-through of how returns work at Aroma.'],
    ];
@endphp

@section('title', ($isAr ? 'دليل العميل' : 'Customer Guide').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'دليل أروما للعميل: مقاسات العبايات، اختيار القصة المناسبة، تعليمات العناية والغسيل، وسياسة الاستبدال والإرجاع.'
    : "Aroma's Customer Guide: abaya sizing, choosing the right fit, care & washing instructions, and our exchange & return policy.")

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/guides.css') }}">
@endpush

@section('content')
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            @if ($showBack)
                <x-back-link :href="$backUrl" class="mb-4" />
            @endif

            <div class="text-center mb-5">
                <span class="aroma-eyebrow d-block mb-2">{{ $brand['name'] }}</span>
                <h1 class="aroma-section-title d-inline-block">{{ $isAr ? 'دليل العميل' : 'Customer Guide' }}</h1>
                <p class="text-aroma-muted mt-3 mb-0" style="max-width:38rem;margin-inline:auto">
                    {{ $isAr
                        ? 'كل ما تحتاجينه للتسوق والارتداء والعناية بمقتنياتك من أروما بثقة تامة.'
                        : "Everything you need to shop, wear, and care for your Aroma pieces with confidence." }}
                </p>
            </div>

            <div class="row g-4">
                @foreach ($cards as [$icon, $route, $title, $desc])
                    <div class="col-md-6">
                        <div class="aroma-card aroma-guide-card p-4 p-lg-5">
                            <div class="aroma-guide-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
                            <h2 class="aroma-guide-card-title">{{ $title }}</h2>
                            <p class="text-aroma-muted mb-0">{{ $desc }}</p>
                            <a href="{{ route($route) }}" class="aroma-guide-card-link stretched-link">
                                {{ $isAr ? 'اطّلعي على الدليل' : 'Read the guide' }}
                                <i class="bi {{ $isAr ? 'bi-chevron-left' : 'bi-chevron-right' }}" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="aroma-trust p-4 p-lg-5 text-center mt-5">
                <p class="mb-3">
                    {{ $isAr ? 'ما زلتِ غير متأكدة؟ تواصلي معنا مباشرة.' : "Still not sure? Talk to us directly." }}
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    @if ($waDigits !== '')
                        <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="btn btn-aroma">
                            <i class="bi bi-whatsapp me-2"></i>{{ $isAr ? 'واتساب' : 'WhatsApp' }}
                        </a>
                    @endif
                    <a href="{{ route('contact') }}" class="btn btn-aroma-outline">
                        <i class="bi bi-envelope me-2"></i>{{ $isAr ? 'تواصلي معنا' : 'Contact Us' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
