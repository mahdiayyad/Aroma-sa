@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';
@endphp

@section('title', ($isAr ? 'الشروط والأحكام' : 'Terms & Conditions').' — '.$brand['name'])

@section('content')
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
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
                    @php
                        $sections = $isAr ? [
                            ['قبول الشروط', 'باستخدامك لمتجر '.$brand['name'].' فإنك توافق على الالتزام بهذه الشروط والأحكام. إذا لم توافق عليها، يُرجى عدم استخدام المتجر.'],
                            ['الطلبات والأسعار', 'جميع الأسعار معروضة بالريال السعودي وشاملة ضريبة القيمة المضافة حيثما ينطبق ذلك. نحتفظ بالحق في تعديل الأسعار وتوافر المنتجات في أي وقت.'],
                            ['الدفع', 'تتم معالجة المدفوعات عبر بوابات دفع آمنة تدعم مدى وآبل باي والبطاقات الائتمانية. لا يتم تأكيد الطلب إلا بعد اكتمال عملية الدفع.'],
                            ['الشحن والتوصيل', 'نسعى لتوصيل طلبك خلال المدة المقدّرة عند إتمام الشراء. قد تختلف مواعيد التسليم حسب الموقع والظروف خارجة عن إرادتنا.'],
                            ['الإرجاع والاستبدال', 'يمكن إرجاع المنتجات غير المستخدمة خلال الفترة المحددة في سياسة الإرجاع، مع الاحتفاظ بالتغليف الأصلي وإثبات الشراء.'],
                            ['الخصوصية', 'نحن نحترم خصوصيتك ونعالج بياناتك الشخصية وفقاً للأنظمة المعمول بها في المملكة العربية السعودية.'],
                            ['تواصل معنا', 'لأي استفسار بخصوص هذه الشروط، يُرجى التواصل معنا عبر '.config('aroma.contact.email', 'hello@aroma.sa').'.'],
                        ] : [
                            ['Acceptance of Terms', 'By using the '.$brand['name'].' store you agree to be bound by these terms and conditions. If you do not agree, please do not use the store.'],
                            ['Orders & Pricing', 'All prices are shown in Saudi Riyals and include VAT where applicable. We reserve the right to change prices and product availability at any time.'],
                            ['Payment', 'Payments are processed through secure gateways supporting Mada, Apple Pay and major credit cards. An order is only confirmed once payment has been completed.'],
                            ['Shipping & Delivery', 'We aim to deliver your order within the estimated timeframe shown at checkout. Delivery times may vary by location and circumstances beyond our control.'],
                            ['Returns & Exchanges', 'Unused products may be returned within the period stated in our returns policy, provided the original packaging and proof of purchase are kept.'],
                            ['Privacy', 'We respect your privacy and process your personal data in accordance with the applicable regulations of the Kingdom of Saudi Arabia.'],
                            ['Contact Us', 'For any question regarding these terms, please contact us at '.config('aroma.contact.email', 'hello@aroma.sa').'.'],
                        ];
                    @endphp

                    @foreach($sections as $i => [$heading, $body])
                        <section @class(['mb-4', 'pb-4 border-bottom' => ! $loop->last])>
                            <h2 class="h5 mb-2" style="color:var(--aroma-brown)">
                                {{ $i + 1 }}. {{ $heading }}
                            </h2>
                            <p class="text-aroma-muted mb-0">{{ $body }}</p>
                        </section>
                    @endforeach
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
