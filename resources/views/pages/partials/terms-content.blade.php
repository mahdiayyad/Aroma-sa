{{--
    The terms & conditions body only — no page chrome (title, "last updated",
    exit link). Included both by the standalone /terms page (pages/terms.blade.php)
    and the checkout payment step's #termsModal (checkout/payment.blade.php),
    so the legal text has exactly one source instead of two copies drifting
    apart. Needs $isAr and $brand in scope — both callers already have them.
--}}
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
