@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';

    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();

    $dos = $isAr
        ? ['غسيل يدوي بماء بارد', 'تجفيف مفروداً بعيداً عن الشمس', 'كي بالبخار عند الإمكان', 'تعليق على شماعة عريضة ومبطنة']
        : ['Hand wash in cold water', 'Dry flat, away from sunlight', 'Steam where possible', 'Hang on a wide, padded hanger'];

    $avoids = $isAr
        ? ['العصر أو الفرك بقوة', 'التجفيف بالمجفف الحراري', 'الكي المباشر على التطريز', 'التخزين في أماكن رطبة']
        : ['Wringing or twisting', 'Tumble drying', 'Ironing directly over embroidery', 'Storing somewhere humid'];

    $cards = $isAr ? [
        ['bi-droplet-half', 'الغسيل', [
            'اغسليها يدوياً بماء بارد ومنظف لطيف خالٍ من المبيّض.',
            'عند استخدام الغسالة، اختاري دورة لطيفة وضعي القطعة داخل كيس غسيل شبكي.',
            'اغسلي القطع المطرزة أو المرصّعة بشكل منفصل، أو فضّلي التنظيف الجاف لها.',
            'تجنبي نقعها لفترة طويلة — بضع دقائق من الغسل اللطيف كافية.',
        ]],
        ['bi-wind', 'التجفيف', [
            'جففيها مفرودة أو على شماعة عريضة للحفاظ على شكل الكتفين.',
            'ابعديها عن أشعة الشمس المباشرة لتفادي بهتان الألوان الداكنة.',
            'اتركيها تجف تماماً قبل ارتدائها أو تخزينها.',
            'لا تعصريها أو تلويها لإزالة الماء الزائد.',
        ]],
        ['bi-thermometer-half', 'الكي والبخار', [
            'البخار أكثر لطفاً من الكي المباشر، خاصة فوق التطريز أو الترتر أو الدانتيل.',
            'إذا استخدمتِ المكواة، اضبطيها على حرارة منخفضة إلى متوسطة واكوي من الداخل.',
            'ضعي قطعة قماش رقيقة بين المكواة وأي منطقة مزخرفة.',
            'لا تكوي مباشرة فوق الطبعات أو الترتر أو الخيوط المعدنية.',
        ]],
        ['bi-archive', 'التخزين', [
            'علّقيها على شماعة عريضة ومبطنة للحفاظ على خط الكتفين.',
            'خزّنيها داخل كيس تخزين قابل للتهوية، بعيداً عن الشمس والرطوبة.',
            'افصلي القطع المرصّعة عن غيرها لتجنّب تشابك الترتر بالأقمشة الأخرى.',
            'أعيدي طيّها أو تعليقها بعد فترات التخزين الطويلة لتفادي ثبات التجاعيد.',
        ]],
    ] : [
        ['bi-droplet-half', 'Wash', [
            'Hand wash in cold water with a mild, bleach-free detergent.',
            'If machine washing, use a gentle/delicate cycle inside a mesh garment bag.',
            'Wash embellished, embroidered, or beaded pieces separately — or prefer professional dry cleaning.',
            "Avoid long soaking — a few gentle minutes is enough.",
        ]],
        ['bi-wind', 'Dry', [
            'Dry flat or on a wide hanger to keep the shoulder shape.',
            'Keep out of direct sunlight — prolonged exposure fades darker fabrics.',
            'Let the piece air dry completely before wearing or storing.',
            'Never wring or twist to remove excess water.',
        ]],
        ['bi-thermometer-half', 'Iron & Steam', [
            'Steam is gentler than direct heat, especially over embroidery, sequins, or lace.',
            'If ironing, use a low-medium setting and iron from the inside out.',
            'Place a thin cloth between the iron and any embellished area.',
            'Never iron directly over prints, sequins, or metallic thread.',
        ]],
        ['bi-archive', 'Store', [
            'Hang on a wide, padded hanger to preserve the shoulder line.',
            'Store in a breathable garment bag, away from direct sunlight and humidity.',
            'Keep embellished pieces separate so sequins or beading don\'t snag other fabrics.',
            'Refold or rehang after long storage to prevent crease lines from setting.',
        ]],
    ];
@endphp

@section('title', ($isAr ? 'العناية والغسيل' : 'Care & Washing').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'دليل أروما للعناية بعبايتك — الغسيل والتجفيف والكي والتخزين بالطريقة الصحيحة.'
    : "Aroma's guide to caring for your abaya — how to wash, dry, iron, and store it the right way.")

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
                <h1 class="aroma-section-title d-inline-block">{{ $isAr ? 'العناية والغسيل' : 'Care & Washing' }}</h1>
            </div>

            @include('pages.guides.partials.subnav', ['active' => 'care', 'isAr' => $isAr])

            <p class="text-aroma-muted text-center mb-4" style="max-width:40rem;margin-inline:auto">
                {{ $isAr
                    ? 'إرشادات عامة تناسب معظم أقمشة العبايات الفاخرة. تحققي دوماً من ملصق العناية الخاص بقطعتك إن وُجد.'
                    : 'General guidance suited to most premium abaya fabrics. Always check your piece\'s own care label where one is provided.' }}
            </p>

            {{-- Quick reference --}}
            <div class="row g-3 mb-5">
                <div class="col-sm-6">
                    <div class="aroma-do-avoid-row flex-column align-items-stretch">
                        @foreach ($dos as $d)
                            <span class="aroma-badge-status aroma-badge-success"><i class="bi bi-check-circle"></i>{{ $d }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="aroma-do-avoid-row flex-column align-items-stretch">
                        @foreach ($avoids as $a)
                            <span class="aroma-badge-status aroma-badge-danger"><i class="bi bi-x-circle"></i>{{ $a }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Detail cards --}}
            <div class="row g-4">
                @foreach ($cards as [$icon, $title, $bullets])
                    <div class="col-md-6">
                        <div class="aroma-card p-4 h-100">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="aroma-guide-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
                                <h2 class="h5 mb-0">{{ $title }}</h2>
                            </div>
                            <ul class="text-aroma-muted small mb-0 ps-3">
                                @foreach ($bullets as $b)
                                    <li class="mb-1">{{ $b }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
