@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';

    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();

    $steps = $isAr ? [
        ['bust', 'محيط الصدر', 'قيسي حول أوسع نقطة في صدرك، مع إبقاء شريط القياس أفقياً ومستريحاً على الجسم دون شد.'],
        ['waist', 'محيط الخصر', 'قيسي حول أضيق نقطة في خصرك الطبيعي — عادة أعلى السرة بقليل.'],
        ['hip', 'محيط الأرداف', 'قيسي حول أوسع نقطة في الأرداف، مع إبقاء القدمين متلاصقتين.'],
        ['length', 'الطول من الكتف', 'قيسي من أعلى الكتف حتى الطول الذي ترغبين أن تصل إليه العباية — عادة حتى الكعبين.'],
    ] : [
        ['bust', 'Bust', "Measure around the fullest part of your bust, keeping the tape level and relaxed — not pulled tight."],
        ['waist', 'Waist', 'Measure around your natural waistline — usually just above the belly button, the narrowest point.'],
        ['hip', 'Hips', 'Measure around the fullest part of your hips, with your feet together.'],
        ['length', 'Shoulder to hem', 'Measure from the top of your shoulder down to where you\'d like the abaya to fall — typically at ankle length.'],
    ];

    $sizes = [
        ['S', '84–88', '66–70', '135–138'],
        ['M', '92–96', '74–78', '138–142'],
        ['L', '100–104', '82–86', '142–146'],
        ['XL', '108–112', '90–94', '146–150'],
    ];
@endphp

@section('title', ($isAr ? 'دليل المقاسات' : 'Size Guide').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'تعلمي كيف تقيسين نفسك بدقة واختاري المقاس المناسب لعباءتك من أروما.'
    : 'Learn how to measure yourself accurately and choose the right size for your Aroma abaya.')

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
                <h1 class="aroma-section-title d-inline-block">{{ $isAr ? 'دليل المقاسات' : 'Size Guide' }}</h1>
            </div>

            @include('pages.guides.partials.subnav', ['active' => 'sizing', 'isAr' => $isAr])

            <p class="text-aroma-muted text-center mb-5" style="max-width:40rem;margin-inline:auto">
                {{ $isAr
                    ? 'هذا دليل عام يساعدك على فهم قياساتك واختيار المقاس الأقرب لجسمك. لكل تصميم مقاساته الدقيقة الموضحة في صفحة المنتج — وإذا لم تتأكدي، فريقنا على واتساب جاهز لمساعدتك قبل الطلب.'
                    : "This is a general guide to help you understand your measurements and pick the closest size. Each design's exact measurements are listed on its own product page — and if you're ever unsure, our team is a WhatsApp message away before you order." }}
            </p>

            {{-- How to measure --}}
            <h2 class="h4 mb-4">{{ $isAr ? 'كيف تقيسين نفسك' : 'How to measure yourself' }}</h2>
            <div class="aroma-measure-steps mb-5">
                @foreach ($steps as $i => [$key, $title, $desc])
                    <div class="aroma-measure-step">
                        <div class="aroma-measure-step-num">{{ $i + 1 }}</div>
                        <div>
                            <div class="fw-semibold mb-1">{{ $title }}</div>
                            <div class="text-aroma-muted small">{{ $desc }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- General size reference --}}
            <h2 class="h4 mb-2">{{ $isAr ? 'مرجع عام للمقاسات' : 'General size reference' }}</h2>
            <p class="text-aroma-muted small mb-4">
                {{ $isAr
                    ? 'نطاقات تقريبية بالسنتيمتر لكل مقاس. تحققي دائماً من قياسات التصميم المحدد في صفحة المنتج قبل الطلب.'
                    : 'Approximate ranges in centimeters for each size. Always check the specific design\'s product page for its exact measurements before ordering.' }}
            </p>
            <div class="aroma-size-grid mb-4">
                @foreach ($sizes as [$label, $bust, $waist, $length])
                    <div class="aroma-card aroma-size-card p-4">
                        <span class="aroma-size-label">{{ $label }}</span>
                        <dl>
                            <dt>{{ $isAr ? 'الصدر' : 'Bust' }}</dt><dd dir="ltr">{{ $bust }} {{ $isAr ? 'سم' : 'cm' }}</dd>
                            <dt>{{ $isAr ? 'الخصر' : 'Waist' }}</dt><dd dir="ltr">{{ $waist }} {{ $isAr ? 'سم' : 'cm' }}</dd>
                            <dt>{{ $isAr ? 'الطول' : 'Length' }}</dt><dd dir="ltr">{{ $length }} {{ $isAr ? 'سم' : 'cm' }}</dd>
                        </dl>
                    </div>
                @endforeach
            </div>

            <div class="aroma-alert aroma-alert-info mb-5" role="note">
                <i class="bi bi-lightbulb" aria-hidden="true"></i>
                <span>
                    {{ $isAr
                        ? 'بين مقاسين؟ اختاري المقاس الأكبر لقصة مريحة وانسيابية أكثر، أو تواصلي معنا لمساعدتك في الاختيار.'
                        : "Between two sizes? Sizing up usually gives a more relaxed, flowing drape — or reach out and we'll help you decide." }}
                </span>
            </div>

            <div class="text-center">
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="{{ route('category.show', [$locale, 'abayas']) }}" class="btn btn-aroma">
                        <i class="bi bi-bag me-2"></i>{{ $isAr ? 'تسوقي العبايات' : 'Shop Abayas' }}
                    </a>
                    <a href="{{ route('guides.fit') }}" class="btn btn-aroma-outline">
                        {{ $isAr ? 'لم تحددي القصة بعد؟ اختاري قصتك' : "Not sure which cut yet? Find Your Fit" }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
