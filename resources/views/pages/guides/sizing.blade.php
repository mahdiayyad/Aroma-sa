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

    // Gulf abaya sizing numbers a size directly by the garment's total
    // length in inches (size 56 = a 56" abaya) — a real regional convention,
    // not an invented scale. [size, sleeve cm, sleeve in, bust cm, bust in, length cm, length in]
    $sizeTable = [
        [50, 73, 29,   55, 22,   127,   50],
        [51, 74, 29.5, 57, 22.5, 129.5, 51],
        [52, 76, 30,   58, 23,   132,   52],
        [53, 77, 30.5, 59, 23.5, 134,   53],
        [54, 78, 31,   60, 24,   137,   54],
        [55, 78, 31,   62, 24.5, 139,   55],
        [56, 80, 31.5, 63, 25,   142,   56],
        [57, 81, 32,   64, 25.5, 144,   57],
        [58, 82, 32.5, 68, 27,   147,   58],
        [59, 83, 33,   69, 27.5, 149,   59],
        [60, 83, 33,   71, 28,   152,   60],
        [61, 83, 33,   72, 28.5, 154,   61],
        [62, 85, 33.5, 73, 29,   157,   62],
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

            {{-- Where an abaya is actually measured --}}
            <h2 class="h4 mb-2">{{ $isAr ? 'من أين تُقاس العباية' : 'Where an abaya is measured' }}</h2>
            <p class="text-aroma-muted small mb-4" style="max-width:40rem">
                {{ $isAr
                    ? 'أربع نقاط تحدد مقاس أي عباية: الطول، عرض الصدر، عرض السفل، وطول الكم. راجعي هذا الرسم قبل مقارنة قياساتك بالجدول أدناه.'
                    : "Four points define any abaya's size: length, bust width, hem width, and sleeve length. Keep these in mind when comparing your measurements to the table below." }}
            </p>
            @include('pages.guides.partials.size-diagram', ['isAr' => $isAr])

            {{-- General size reference --}}
            <h2 class="h4 mb-2 mt-5">{{ $isAr ? 'جدول المقاسات الكامل' : 'Full size chart' }}</h2>
            <p class="text-aroma-muted small mb-3">
                {{ $isAr
                    ? 'يُرمز لكل مقاس برقم يعادل طول العباية بالإنش تقريباً — رقم أكبر يعني عباية أطول. نطاقات تقريبية؛ تحققي دائماً من قياسات التصميم المحدد في صفحة المنتج.'
                    : "Each size is numbered for the abaya's approximate length in inches — a higher number means a longer abaya. These are general figures; always check the specific design's product page for its exact measurements." }}
            </p>
            <p class="aroma-size-table-hint">
                <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                {{ $isAr ? 'مرري يميناً ويساراً لرؤية جميع القياسات' : 'Swipe to see all measurements' }}
            </p>
            <div class="aroma-table-responsive aroma-size-table-wrap mb-4">
                <table class="aroma-table aroma-size-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="aroma-size-table-size-col">{{ $isAr ? 'المقاس' : 'Size' }}</th>
                            <th colspan="2">{{ $isAr ? 'طول الكم' : 'Sleeve length' }}</th>
                            <th colspan="2" class="aroma-size-table-divider">{{ $isAr ? 'عرض الصدر' : 'Bust width' }}</th>
                            <th colspan="2" class="aroma-size-table-divider">{{ $isAr ? 'الطول' : 'Length' }}</th>
                        </tr>
                        <tr>
                            <th class="aroma-size-table-unit">{{ $isAr ? 'سم' : 'cm' }}</th>
                            <th class="aroma-size-table-unit">{{ $isAr ? 'إنش' : 'in' }}</th>
                            <th class="aroma-size-table-unit aroma-size-table-divider">{{ $isAr ? 'سم' : 'cm' }}</th>
                            <th class="aroma-size-table-unit">{{ $isAr ? 'إنش' : 'in' }}</th>
                            <th class="aroma-size-table-unit aroma-size-table-divider">{{ $isAr ? 'سم' : 'cm' }}</th>
                            <th class="aroma-size-table-unit">{{ $isAr ? 'إنش' : 'in' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sizeTable as [$size, $sleeveCm, $sleeveIn, $bustCm, $bustIn, $lengthCm, $lengthIn])
                            <tr>
                                <td class="aroma-size-table-size-col">{{ $size }}</td>
                                <td dir="ltr">{{ $sleeveCm }}</td>
                                <td dir="ltr">{{ $sleeveIn }}</td>
                                <td dir="ltr" class="aroma-size-table-divider">{{ $bustCm }}</td>
                                <td dir="ltr">{{ $bustIn }}</td>
                                <td dir="ltr" class="aroma-size-table-divider">{{ $lengthCm }}</td>
                                <td dir="ltr">{{ $lengthIn }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
