@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';

    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();

    $preferences = $isAr ? [
        ['bi-wind', 'راحة الحركة طوال اليوم', 'القصة الكيمونو المفتوحة من الأمام، بأقمشة خفيفة كالشيفون أو الكتان — تسمح بحرية حركة كاملة وتُلبس فوق أي إطلالة بسهولة.'],
        ['bi-briefcase', 'إطلالة أنيقة ومهيّبة', 'القصة المغلقة أو الملفوفة مع تحديد عند الخصر أو حزام — مثالية للعمل والمناسبات اليومية الرسمية.'],
        ['bi-arrows-vertical', 'قصيرة القامة وتبحثين عن إطالة', 'الخطوط الرأسية والقصات المستقيمة، مع طول مضبوط بدقة — راجعي دليل المقاسات لتحديد الطول الأنسب لكِ.'],
        ['bi-flower1', 'مناسبات خاصة', 'أقمشة فاخرة كالحرير أو المخمل، مع تطريز أو لمسات مزخرفة — لإطلالة لافتة في الأعراس والاحتفالات.'],
    ] : [
        ['bi-wind', 'All-day ease of movement', 'An open-front kimono cut in a lighter fabric like chiffon or linen — full freedom to move, and easy to layer over any outfit.'],
        ['bi-briefcase', 'Structured & polished', 'A closed or wrap-style cut with definition at the waist, or a belt — well suited to work and formal daily occasions.'],
        ['bi-arrows-vertical', 'Petite frame, want elongation', 'Clean vertical lines and a straighter cut, with a precisely measured length — see our Size Guide to find the length that\'s right for you.'],
        ['bi-flower1', 'Special occasions', 'Richer fabrics like silk or velvet, with embroidery or embellished details — for a look that stands out at weddings and celebrations.'],
    ];

    $fabrics = $isAr ? [
        ['الشيفون', 'خفيف وشفاف بحركة انسيابية — للإطلالات اليومية العصرية.'],
        ['الكريب', 'كثافة متوسطة وسقوط أنيق دون شفافية — الأكثر تنوعاً للاستخدام اليومي.'],
        ['الحرير', 'ملمس فاخر ولمعان راقٍ — للمناسبات والاحتفالات.'],
        ['المخمل', 'دافئ وفخم بملمس كثيف — لموسم الشتاء والمناسبات المسائية.'],
        ['الكتان', 'خفيف ومسامي يناسب الأجواء الحارة — للراحة اليومية.'],
        ['النيدا', 'أقمشة كثيفة مطاطة الملمس تحافظ على شكلها — قصات هيكلية ورسمية.'],
    ] : [
        ['Chiffon', 'Light and airy with a flowing drape — for everyday, breezy looks.'],
        ['Crepe', 'Medium weight with a clean, opaque fall — the most versatile for daily wear.'],
        ['Silk', 'A luxurious texture with a soft sheen — for occasions and celebrations.'],
        ['Velvet', 'Warm and rich with body — for winter and evening occasions.'],
        ['Linen', 'Light and breathable — for everyday comfort in warmer weather.'],
        ['Nida', 'A denser, structured fabric that holds its shape — for tailored, formal cuts.'],
    ];
@endphp

@section('title', ($isAr ? 'اختاري قصتك' : 'Find Your Fit').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'دليل أروما لاختيار قصة العباية المناسبة لكِ — بأسلوب إيجابي وأنيق يليق بكِ.'
    : "Aroma's guide to choosing the abaya cut that suits you — positive, elegant, and made for every body.")

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/components/guides.css') }}">
@endpush

@section('content')
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @if ($showBack)
                <x-back-link :href="$backUrl" class="mb-4" />
            @endif

            <div class="text-center mb-4">
                <span class="aroma-eyebrow d-block mb-2">{{ $brand['name'] }}</span>
                <h1 class="aroma-section-title d-inline-block">{{ $isAr ? 'اختاري قصتك' : 'Find Your Fit' }}</h1>
            </div>

            @include('pages.guides.partials.subnav', ['active' => 'fit', 'isAr' => $isAr])

            <p class="text-aroma-muted text-center mb-5" style="max-width:42rem;margin-inline:auto">
                {{ $isAr
                    ? 'كل قوام يستحق أن يشعر صاحبه بالجمال والراحة معاً. هذا الدليل ليس عن إخفاء شيء، بل عن اكتشاف القصة التي تشبهك أكثر — حسب يومك، حركتك، ومناسباتك.'
                    : "Every body deserves to feel beautiful and comfortable at once. This isn't about hiding anything — it's about discovering the cut that feels most like you, based on your day, your movement, and your occasions." }}
            </p>

            <h2 class="h4 mb-4">{{ $isAr ? 'ابدئي من هنا' : 'Start here' }}</h2>
            <div class="row g-4 mb-5">
                @foreach ($preferences as [$icon, $title, $desc])
                    <div class="col-md-6">
                        <div class="aroma-card p-4 h-100">
                            <div class="aroma-guide-icon mb-3"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
                            <h3 class="h6 mb-2">{{ $title }}</h3>
                            <p class="text-aroma-muted small mb-0">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <h2 class="h4 mb-4">{{ $isAr ? 'دليل الأقمشة السريع' : 'A quick fabric glossary' }}</h2>
            <div class="row g-3 mb-5">
                @foreach ($fabrics as [$name, $desc])
                    <div class="col-sm-6 col-lg-4">
                        <div class="d-flex gap-2 align-items-start">
                            <i class="bi bi-check2 text-aroma-brown mt-1" aria-hidden="true"></i>
                            <div>
                                <span class="fw-semibold">{{ $name }}</span>
                                <div class="text-aroma-muted small">{{ $desc }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="aroma-trust p-4 p-lg-5 text-center">
                <p class="mb-3">
                    {{ $isAr
                        ? 'وجدتِ القصة التي تناسبك؟ اطّلعي على مقاسك بدقة، أو تسوقي مجموعة العبايات كاملة.'
                        : "Found the cut that speaks to you? Get your exact size, or browse the full abaya collection." }}
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="{{ route('guides.sizing') }}" class="btn btn-aroma-outline">
                        <i class="bi bi-rulers me-2"></i>{{ $isAr ? 'دليل المقاسات' : 'Size Guide' }}
                    </a>
                    <a href="{{ route('category.show', [$locale, 'abayas']) }}" class="btn btn-aroma">
                        <i class="bi bi-bag me-2"></i>{{ $isAr ? 'تسوقي العبايات' : 'Shop Abayas' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
