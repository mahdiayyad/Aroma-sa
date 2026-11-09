@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';
    $waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp'));

    // Footer-reachable from checkout — same referrer-guarded back link as
    // privacy-policy.blade.php.
    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();

    // Every answer restates privacy-policy.blade.php, the Customer Guide or
    // what checkout actually does — never a new claim. Delivery times come
    // from the same translation keys checkout shows, so they can't drift.
    $deliveryAnswer = ($isAr ? 'داخل المملكة: ' : 'Within Saudi Arabia: ').__('delivery.notice').' '
        .($isAr ? 'خارج المملكة: ' : 'Outside Saudi Arabia: ').__('checkout.shipping_days.international').'. '
        .($isAr
            ? 'وقد تتسبب ظروف خارجة عن إرادتنا — كالجمارك أو الأحوال الجوية أو تعطل خدمات الشحن — في تمديد هذه المدة أحياناً.'
            : 'Circumstances beyond our control — customs, weather, or courier disruptions — may occasionally extend this timeframe.');

    $returnsLink = ['route' => 'guides.returns', 'label' => $isAr ? 'دليل الاستبدال والإرجاع خطوة بخطوة' : 'Step-by-step Exchange & Returns guide'];

    $groups = $isAr ? [
        ['title' => 'الشحن والتوصيل', 'items' => [
            ['q' => 'كم تستغرق مدة التوصيل؟', 'a' => $deliveryAnswer],
            ['q' => 'من يتولى الشحن وكيف أتتبع طلبي؟', 'a' => 'يتم الشحن من خلال شركة أرامكس، وتصل رسالة إشعار تتضمن تفاصيل تتبع الشحنة فور شحن الطلب.'],
            ['q' => 'هل تشحنون خارج السعودية؟', 'a' => 'نعم، يمكن إدخال عنوان خارج المملكة عند إتمام الطلب.'],
            ['q' => 'كم تكلفة الشحن؟', 'a' => 'تُحسب تكلفة الشحن حسب عنوان التوصيل، وتظهر في صفحة مراجعة الطلب قبل الدفع.'],
        ]],
        ['title' => 'الإرجاع والاستبدال', 'items' => [
            ['q' => 'ما مدة الإرجاع وشروطه؟', 'a' => 'يمكن تقديم طلب الإرجاع خلال 3 أيام من تاريخ استلام الطلب، على أن يكون المنتج بحالته الأصلية غير المستخدمة وبتغليفه الأصلي.', 'link' => $returnsLink],
            ['q' => 'هل توجد رسوم على الإرجاع؟', 'a' => 'لا إذا وصل المنتج تالفاً أو كان خاطئاً أو غير مطابق للوصف. في الحالات الأخرى تُخصم رسوم معالجة إرجاع قدرها 50 ريال سعودي من قيمة المبلغ المسترد لتغطية تكاليف معالجة الإرجاع والشحن.'],
            ['q' => 'كيف يعود المبلغ؟', 'a' => 'تتم إعادة المبلغ إلى وسيلة الدفع الأصلية بعد استلام المنتج المرتجع والتحقق من حالته.'],
            ['q' => 'ما المنتجات التي لا يمكن إرجاعها؟', 'a' => 'المنتجات القابلة للتلف والمنتجات المخصصة أو المُصنّعة حسب الطلب.'],
            ['q' => 'كيف أبدأ طلب إرجاع أو استبدال؟', 'a' => 'بمراسلتنا عبر واتساب أو البريد الإلكتروني خلال 3 أيام من تاريخ استلام الطلب.', 'link' => $returnsLink],
        ]],
        ['title' => 'الدفع', 'items' => [
            ['q' => 'ما طرق الدفع المتاحة؟', 'a' => 'مدى وفيزا وماستركارد وآبل باي، وتظهر في صفحة الدفع جميع الخيارات المتاحة لطلبك، بما فيها الدفع الآجل عبر تمارا عند توفره.'],
            ['q' => 'هل بيانات بطاقتي آمنة؟', 'a' => 'تتم معالجة بيانات الدفع بشكل آمن من قبل شركاء الدفع المرخّصين لدينا، ولا نقوم بتخزين بيانات البطاقة الكاملة.'],
        ]],
        ['title' => 'المقاسات والعناية', 'items' => [
            ['q' => 'كيف أعرف مقاسي؟', 'a' => 'يشرح دليل المقاسات طريقة أخذ القياسات واختيار المقاس الأنسب لكل قطعة.', 'link' => ['route' => 'guides.sizing', 'label' => 'دليل المقاسات']],
            ['q' => 'كيف أعتني بالقطعة؟', 'a' => 'تجد تعليمات العناية والغسيل في دليل العناية.', 'link' => ['route' => 'guides.care', 'label' => 'العناية والغسيل']],
        ]],
        ['title' => 'الطلبات والهدايا', 'items' => [
            ['q' => 'هل يمكن إرسال الطلب كهدية؟', 'a' => 'نعم، عند إتمام الطلب يمكن تحديد أن الطلب هدية وإدخال عنوان المستلم وإضافة رسالة إهداء وتغليف وبطاقة تهنئة، كما يمكن إخفاء اسم المرسل. وتظهر أي رسوم للتغليف أو البطاقة قبل الدفع.'],
            ['q' => 'هل يجب إنشاء حساب للطلب؟', 'a' => 'لا، يمكن إتمام الطلب كزائر، ويتيح تسجيل الدخول متابعة الطلبات من صفحة الحساب.'],
            ['q' => 'كيف أتواصل معكم؟', 'a' => 'عبر واتساب أو البريد الإلكتروني، ونحرص على الرد على كل رسالة خلال 24 ساعة.', 'link' => ['route' => 'contact', 'label' => 'تواصل معنا']],
        ]],
    ] : [
        ['title' => 'Shipping & delivery', 'items' => [
            ['q' => 'How long does delivery take?', 'a' => $deliveryAnswer],
            ['q' => 'Who ships my order and how do I track it?', 'a' => "Shipping is handled through Aramex. You'll receive a notification with tracking details once your order has shipped."],
            ['q' => 'Do you ship outside Saudi Arabia?', 'a' => 'Yes, you can enter an address outside the Kingdom at checkout.'],
            ['q' => 'How much is shipping?', 'a' => 'Shipping is calculated from your delivery address and shown on the order review page before you pay.'],
        ]],
        ['title' => 'Returns & exchanges', 'items' => [
            ['q' => 'What is the return window and what are the conditions?', 'a' => 'You may submit a return request within 3 days of receiving your order, with the product in its original, unused condition and packaging.', 'link' => $returnsLink],
            ['q' => 'Is there a fee for returns?', 'a' => 'Not if the item arrived damaged, was the wrong item, or was not as described. In all other cases, a return handling fee of 50 SAR is deducted from your refund to cover return processing and shipping costs.'],
            ['q' => 'How do I get my money back?', 'a' => 'Refunds are issued to the original payment method once the returned item has been received and inspected.'],
            ['q' => "What can't be returned?", 'a' => 'Perishable items (fresh flowers) and personalized or made-to-order products.'],
            ['q' => 'How do I start a return or exchange?', 'a' => 'Message us on WhatsApp or email within 3 days of receiving your order.', 'link' => $returnsLink],
        ]],
        ['title' => 'Payment', 'items' => [
            ['q' => 'Which payment methods do you accept?', 'a' => 'Mada, Visa, Mastercard and Apple Pay. The payment page shows every option available for your order, including paying later with Tamara where available.'],
            ['q' => 'Are my card details safe?', 'a' => 'Payments are processed securely by our licensed payment partners, and we do not store your full card details.'],
        ]],
        ['title' => 'Sizing & care', 'items' => [
            ['q' => 'How do I find my size?', 'a' => 'Our Size Guide explains how to measure yourself and find the size that fits.', 'link' => ['route' => 'guides.sizing', 'label' => 'Size Guide']],
            ['q' => 'How do I care for my piece?', 'a' => 'Care and washing instructions are in our care guide.', 'link' => ['route' => 'guides.care', 'label' => 'Care & Washing']],
        ]],
        ['title' => 'Orders & gifts', 'items' => [
            ['q' => 'Can I send my order as a gift?', 'a' => "Yes. At checkout you can mark the order as a gift, enter the recipient's address, and add a gift message, wrapping and a greeting card. You can also keep the sender's name hidden. Any wrapping or card fee is shown before you pay."],
            ['q' => 'Do I need an account to order?', 'a' => 'No, you can check out as a guest. Signing in lets you follow your orders from your account page.'],
            ['q' => 'How can I contact you?', 'a' => 'By WhatsApp or email, and we aim to acknowledge every message within 24 hours.', 'link' => ['route' => 'contact', 'label' => 'Contact us']],
        ]],
    ];
@endphp

@section('title', ($isAr ? 'الأسئلة الشائعة' : 'Frequently Asked Questions').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'إجابات سريعة عن الشحن والإرجاع والدفع والمقاسات والهدايا في أروما.'
    : 'Quick answers about shipping, returns, payment, sizing and gifting at Aroma.')

@section('content')
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @if ($showBack)
                <x-back-link :href="$backUrl" class="mb-4" />
            @endif

            <div class="text-center mb-5">
                <span class="aroma-eyebrow d-block mb-2">{{ $brand['name'] }}</span>
                <h1 class="aroma-section-title d-inline-block">{{ $isAr ? 'الأسئلة الشائعة' : 'Frequently Asked Questions' }}</h1>
            </div>

            @foreach ($groups as $g => $group)
                <h2 class="h5 mb-3 {{ $loop->first ? '' : 'mt-5' }}">{{ $group['title'] }}</h2>
                <div class="accordion aroma-policy-accordion" id="faqGroup{{ $g }}">
                    @foreach ($group['items'] as $i => $item)
                        @php($open = $loop->parent->first && $loop->first)
                        <div class="accordion-item">
                            <h3 class="accordion-header" id="faq-heading-{{ $g }}-{{ $i }}">
                                <button class="accordion-button {{ $open ? '' : 'collapsed' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#faq-{{ $g }}-{{ $i }}"
                                        aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="faq-{{ $g }}-{{ $i }}">
                                    {{ $item['q'] }}
                                </button>
                            </h3>
                            <div id="faq-{{ $g }}-{{ $i }}" class="accordion-collapse collapse {{ $open ? 'show' : '' }}"
                                 aria-labelledby="faq-heading-{{ $g }}-{{ $i }}" data-bs-parent="#faqGroup{{ $g }}">
                                <div class="accordion-body">
                                    <p class="text-aroma-muted {{ isset($item['link']) ? 'mb-3' : 'mb-0' }}" style="line-height:1.9">{{ $item['a'] }}</p>
                                    @isset($item['link'])
                                        <a href="{{ route($item['link']['route']) }}" class="fw-semibold">{{ $item['link']['label'] }}</a>
                                    @endisset
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div class="aroma-trust p-4 p-lg-5 text-center mt-5">
                <p class="mb-3">{{ $isAr ? 'لم تجد إجابتك؟ تواصل معنا مباشرة.' : "Didn't find your answer? Talk to us directly." }}</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    @if ($waDigits !== '')
                        <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="btn btn-aroma">
                            <i class="bi bi-whatsapp me-2"></i>{{ $isAr ? 'واتساب' : 'WhatsApp' }}
                        </a>
                    @endif
                    <a href="{{ route('contact') }}" class="btn btn-aroma-outline">
                        <i class="bi bi-envelope me-2"></i>{{ $isAr ? 'تواصل معنا' : 'Contact Us' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
