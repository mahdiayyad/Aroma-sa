@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';
    $waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp'));

    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();

    // Every fact below is decomposed directly from privacy-policy.blade.php's
    // 'returns' and 'complaints' accordion sections — reorganized for
    // scannability, never reworded into a new claim. No day-count return
    // window is stated anywhere in the app, so none is invented here either.
    $steps = $isAr ? [
        ['bi-chat-dots', 'تواصلي معنا', 'راسلينا عبر واتساب أو البريد الإلكتروني خلال فترة الإرجاع الموضحة عند إتمام الطلب.'],
        ['bi-box-seam', 'جهّزي القطعة', 'احتفظي بالمنتج بحالته الأصلية غير المستخدمة، وبتغليفه الأصلي.'],
        ['bi-clock-history', 'نستلم ونراجع', 'فريقنا يرد خلال 24 ساعة، ويقوم بمراجعة القطعة المرتجعة عند استلامها.'],
        ['bi-arrow-repeat', 'استرداد المبلغ', 'يُعاد المبلغ إلى وسيلة الدفع الأصلية بعد التحقق من حالة المنتج، بعد خصم رسوم المعالجة.'],
    ] : [
        ['bi-chat-dots', 'Contact us', 'Message us on WhatsApp or email within the return period stated at checkout.'],
        ['bi-box-seam', 'Pack it up', "Keep the item in its original, unused condition, with its original packaging."],
        ['bi-clock-history', 'We review it', "We acknowledge every message within 24 hours, and review the item once we receive it."],
        ['bi-arrow-repeat', 'Refund issued', "Refunded to your original payment method once it's received and inspected, minus the handling fee."],
    ];

    $eligible = $isAr
        ? ['منتجات بحالتها الأصلية غير المستخدمة', 'مع الاحتفاظ بالتغليف الأصلي', 'خلال فترة الإرجاع الموضحة عند إتمام الطلب']
        : ['Items in original, unused condition', 'With original packaging kept', 'Within the return period stated at checkout'];

    $notEligible = $isAr
        ? ['المنتجات القابلة للتلف (كالزهور الطازجة)', 'المنتجات المخصصة أو المُصنّعة حسب الطلب']
        : ['Perishable items (fresh flowers)', 'Personalized or made-to-order products'];
@endphp

@section('title', ($isAr ? 'الاستبدال والإرجاع' : 'Exchange & Returns').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'شرح واضح لكيفية عمل الاستبدال والإرجاع في أروما.'
    : "A clear, plain-language walk-through of how exchanges and returns work at Aroma.")

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
                <h1 class="aroma-section-title d-inline-block">{{ $isAr ? 'الاستبدال والإرجاع' : 'Exchange & Returns' }}</h1>
            </div>

            @include('pages.guides.partials.subnav', ['active' => 'returns', 'isAr' => $isAr])

            <div class="aroma-alert aroma-alert-info mb-5" role="note">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>
                    {{ $isAr ? 'هذا شرح مبسّط لسياستنا. ' : 'This is a plain-language summary. ' }}
                    <a href="{{ route('privacy-policy') }}#returns">{{ $isAr ? 'اطّلعي على النص الكامل لسياسة الإرجاع' : 'Read the full Returns Policy' }}</a>
                    {{ $isAr ? ' في صفحة السياسات والخصوصية.' : ' on our Policies & Privacy page.' }}
                </span>
            </div>

            <h2 class="h4 mb-4">{{ $isAr ? 'كيف تتم العملية' : 'How it works' }}</h2>
            <div class="aroma-return-steps mb-5">
                @foreach ($steps as $i => [$icon, $title, $desc])
                    <div class="aroma-return-step">
                        <div class="aroma-return-step-num">{{ $i + 1 }}</div>
                        <div class="fw-semibold d-flex align-items-center gap-2">
                            <i class="bi {{ $icon }} text-aroma-brown" aria-hidden="true"></i>{{ $title }}
                        </div>
                        <p class="text-aroma-muted small mb-0">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="aroma-card p-4 h-100">
                        <h3 class="h6 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle text-success" aria-hidden="true"></i>
                            {{ $isAr ? 'مؤهّل للإرجاع' : 'Eligible for return' }}
                        </h3>
                        <ul class="list-unstyled mb-0">
                            @foreach ($eligible as $e)
                                <li class="mb-2"><span class="aroma-badge-status aroma-badge-success">{{ $e }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="aroma-card p-4 h-100">
                        <h3 class="h6 mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-x-circle text-danger" aria-hidden="true"></i>
                            {{ $isAr ? 'غير مؤهّل للإرجاع' : 'Not eligible for return' }}
                        </h3>
                        <ul class="list-unstyled mb-0">
                            @foreach ($notEligible as $n)
                                <li class="mb-2"><span class="aroma-badge-status aroma-badge-danger">{{ $n }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="aroma-alert aroma-alert-warning mb-5" role="note">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <span>
                    {{ $isAr
                        ? 'يُخصم رسم معالجة إرجاع قدره 50 ريال سعودي من قيمة المبلغ المسترد لتغطية تكاليف المعالجة والشحن، بصرف النظر عن سبب الإرجاع.'
                        : 'A return handling fee of 50 SAR is deducted from your refund to cover return processing and shipping costs, regardless of the reason for return.' }}
                </span>
            </div>

            <div class="aroma-trust p-4 p-lg-5 text-center">
                <p class="mb-3">
                    {{ $isAr ? 'جاهزة لبدء عملية إرجاع أو استبدال؟' : 'Ready to start a return or exchange?' }}
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    @if ($waDigits !== '')
                        <a href="https://wa.me/{{ $waDigits }}?text={{ rawurlencode($isAr ? 'مرحباً أروما، أرغب في إرجاع أو استبدال منتج من طلبي.' : 'Hello Aroma, I would like to return or exchange an item from my order.') }}"
                           target="_blank" rel="noopener" class="btn btn-aroma">
                            <i class="bi bi-whatsapp me-2"></i>{{ $isAr ? 'ابدئي عبر واتساب' : 'Start on WhatsApp' }}
                        </a>
                    @endif
                    <a href="{{ route('contact') }}" class="btn btn-aroma-outline">
                        <i class="bi bi-envelope me-2"></i>{{ $isAr ? 'راسلينا بالبريد' : 'Email Us' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
