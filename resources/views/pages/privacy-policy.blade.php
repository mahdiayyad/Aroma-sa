@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';
    $contactEmail = config('aroma.contact.email');
    $waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp'));

    // Same footer-reachable-from-checkout gap as terms.blade.php — see the
    // comment there for why url()->previous() is the right signal here.
    $backUrl = url()->previous();
    $showBack = $backUrl !== url()->current()
        && $backUrl !== url('/')
        && parse_url($backUrl, PHP_URL_HOST) === request()->getHost();
@endphp

@section('title', ($isAr ? 'السياسات والخصوصية' : 'Policies & Privacy').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'سياسات أروما للشحن والإرجاع والشكاوى والخصوصية وحماية بيانات العملاء.'
    : "Aroma's shipping, returns, complaints & suggestions, and privacy policies.")
@section('robots', 'index, follow')

@section('content')
@php
    $sections = [
        [
            'id' => 'shipping',
            'title' => $isAr ? 'سياسة الشحن' : 'Shipping Policy',
            'paragraphs' => $isAr ? [
                'نسعى لمعالجة وشحن كل طلب في أسرع وقت ممكن. يستغرق التوصيل عادة ما يصل إلى 15 يوم عمل تقريباً من تاريخ تأكيد الطلب، وذلك حسب موقع العميل داخل المملكة العربية السعودية وظروف شركة الشحن.',
                'تصل رسالة إشعار تتضمن تفاصيل تتبع الشحنة فور شحن الطلب. وقد تتسبب ظروف خارجة عن إرادتنا — كالجمارك أو الأحوال الجوية أو تعطل خدمات الشحن — في تمديد هذه المدة أحياناً، وسنحرص على إبقاء العميل على اطلاع بأي تأخير جوهري.',
            ] : [
                'We aim to process and dispatch every order as quickly as possible. Delivery typically takes up to approximately 15 business days from the date your order is confirmed, depending on your location within the Kingdom of Saudi Arabia and prevailing courier conditions.',
                "You'll receive a notification with tracking details once your order has shipped. Delays caused by circumstances beyond our control — customs, weather, or courier disruptions — may occasionally extend this timeframe, and we'll keep you informed of any significant delay.",
            ],
        ],
        [
            'id' => 'returns',
            'title' => $isAr ? 'سياسة الإرجاع' : 'Returns Policy',
            'paragraphs' => $isAr ? [
                'نتمنى أن تكون تجربة الشراء من أروما مُرضية تماماً. في حال الرغبة بإرجاع منتج، يُرجى التواصل معنا خلال فترة الإرجاع الموضحة عند إتمام الطلب، على أن يكون المنتج بحالته الأصلية غير المستخدمة وبتغليفه الأصلي.',
                'سيتم خصم رسوم معالجة إرجاع قدرها 50 ريال سعودي من قيمة المبلغ المسترد، لتغطية تكاليف معالجة الإرجاع والشحن، بصرف النظر عن سبب الإرجاع. تتم إعادة المبلغ إلى وسيلة الدفع الأصلية بعد استلام المنتج المرتجع والتحقق من حالته.',
                'لا يشمل الإرجاع المنتجات القابلة للتلف (كالزهور الطازجة) والمنتجات المخصصة أو المُصنّعة حسب الطلب.',
            ] : [
                'We want you to love your Aroma purchase. If you wish to return an item, please contact us within the return period stated at checkout, with the product in its original, unused condition and packaging.',
                'A return handling fee of 50 SAR will be deducted from your refund to cover return processing and shipping costs, regardless of the reason for return. Refunds are issued to the original payment method once the returned item has been received and inspected.',
                'Perishable items (fresh flowers) and personalized or made-to-order products are not eligible for return.',
            ],
            'cta' => ['route' => 'guides.returns', 'label' => $isAr ? 'اطّلعي على دليل الاستبدال والإرجاع خطوة بخطوة' : 'See our step-by-step Exchange & Returns guide'],
        ],
        [
            'id' => 'complaints',
            'title' => $isAr ? 'سياسة الشكاوى والاقتراحات' : 'Complaints & Suggestions Policy',
            'paragraphs' => $isAr ? [
                'ملاحظات عملائنا تساعدنا على التطور والتحسّن المستمر. عند وجود أي شكوى أو استفسار أو اقتراح، فإن فريق خدمة العملاء لدينا جاهز للمساعدة.',
                'يمكن التواصل معنا عبر واتساب أو البريد الإلكتروني، ونحرص على الرد على كل رسالة خلال 24 ساعة، ومعالجة الشكاوى في أسرع وقت ممكن. نأخذ كل شكوى على محمل الجد ونستخدم الملاحظات لتطوير منتجاتنا وخدماتنا باستمرار.',
            ] : [
                "Your feedback helps us grow. If you have a complaint, question, or suggestion, our customer care team is here to help.",
                "You can reach us via WhatsApp or email, and we aim to acknowledge every message within 24 hours and resolve complaints as quickly as possible. We treat every complaint seriously and use your feedback to continuously improve our products and service.",
            ],
        ],
        [
            'id' => 'privacy',
            'title' => $isAr ? 'سياسة الخصوصية وحماية بيانات العملاء' : 'Privacy Policy & Customer Data Protection',
            'paragraphs' => $isAr ? [
                'في أروما، تُعد حماية البيانات الشخصية لعملائنا مسؤولية نأخذها على محمل الجد.',
                'المعلومات التي نجمعها: عند إنشاء حساب، أو إتمام طلب، أو التواصل معنا، قد نقوم بجمع الاسم، والبريد الإلكتروني، ورقم الهاتف، وعناوين الشحن والفوترة، والمعلومات المتعلقة بالدفع — والتي تتم معالجتها بشكل آمن من قبل شركاء الدفع المرخّصين لدينا، ولا نقوم بتخزين بيانات البطاقة الكاملة.',
                'كيفية استخدامنا للمعلومات: نستخدم البيانات لمعالجة الطلبات وتوصيلها، والتواصل بخصوص المشتريات، وتقديم خدمة العملاء، وتحسين منتجاتنا وخدماتنا، وعند الموافقة، إرسال رسائل تسويقية يمكن إلغاء الاشتراك فيها في أي وقت.',
                'مشاركة المعلومات: نشارك المعلومات فقط مع أطراف ثالثة موثوقة ضرورية لإتمام الطلب، مثل معالجات الدفع (مدى، فيزا، ماستركارد، آبل باي، ميسر)، ومزودي خدمات الدفع الآجل (تابي، تمارا)، وشركات الشحن. نحن لا نبيع البيانات الشخصية لأي طرف ثالث.',
                'أمن البيانات: نطبّق تدابير تقنية وتنظيمية مناسبة لحماية البيانات من الوصول غير المصرح به أو الفقدان أو سوء الاستخدام، بما يتوافق مع متطلبات نظام حماية البيانات الشخصية السعودي (PDPL).',
                'حقوق العميل: يمكن طلب الاطلاع على البيانات الشخصية أو تصحيحها أو حذفها، كما يمكن سحب الموافقة على الرسائل التسويقية في أي وقت من خلال التواصل معنا.',
                'ملفات تعريف الارتباط (الكوكيز): يستخدم موقعنا ملفات تعريف الارتباط للحفاظ على تسجيل الدخول، وتذكر سلة التسوق والتفضيلات، وفهم كيفية استخدام الموقع بما يساعدنا على تحسين التجربة.',
                'قد نقوم بتحديث هذه السياسة من وقت لآخر، وستكون النسخة الأحدث متوفرة دائماً على هذه الصفحة. لأي استفسار حول كيفية تعاملنا مع البيانات، يُرجى التواصل معنا عبر '.$contactEmail.'.',
            ] : [
                'At Aroma, protecting your personal data is a responsibility we take seriously.',
                "Information we collect: when you create an account, place an order, or contact us, we may collect your name, email address, phone number, shipping and billing addresses, and payment-related information — processed securely by our licensed payment partners. We do not store your full card details.",
                'How we use your information: we use your data to process and deliver your orders, communicate with you about your purchases, provide customer support, improve our products and services, and — where you have opted in — send marketing communications you may unsubscribe from at any time.',
                'Sharing your information: we share your information only with trusted third parties necessary to fulfill your order, such as payment processors (Mada, Visa, Mastercard, Apple Pay, Moyasar), buy-now-pay-later providers (Tabby, Tamara), and shipping couriers. We do not sell your personal data to third parties.',
                'Data security: we apply appropriate technical and organizational measures to protect your data against unauthorized access, loss, or misuse, consistent with the requirements of the Saudi Personal Data Protection Law (PDPL).',
                'Your rights: you may request access to, correction of, or deletion of your personal data, and may withdraw consent to marketing communications at any time by contacting us.',
                'Cookies: our website uses cookies to keep you signed in, remember your cart and preferences, and understand how our site is used — helping us improve your experience.',
                'We may update this policy from time to time; the latest version will always be available on this page. For any question about how we handle your data, please contact us at '.$contactEmail.'.',
            ],
        ],
    ];
@endphp

<div class="container my-5">
    @if ($showBack)
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <x-back-link :href="$backUrl" class="mb-4" />
            </div>
        </div>
    @endif

    <div class="text-center mb-5">
        <span class="aroma-eyebrow d-block mb-2">{{ $brand['name'] }}</span>
        <h1 class="aroma-section-title d-inline-block">{{ $isAr ? 'السياسات والخصوصية' : 'Policies & Privacy' }}</h1>
        <p class="text-aroma-muted mt-3 mb-0">
            {{ $isAr ? 'آخر تحديث' : 'Last updated' }}: {{ now()->translatedFormat('j F Y') }}
        </p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="accordion aroma-policy-accordion" id="policyAccordion">
                @foreach ($sections as $i => $s)
                    <div class="accordion-item" id="{{ $s['id'] }}" style="scroll-margin-top:90px">
                        <h2 class="accordion-header" id="heading-{{ $s['id'] }}">
                            <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#collapse-{{ $s['id'] }}"
                                    aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="collapse-{{ $s['id'] }}">
                                {{ $s['title'] }}
                            </button>
                        </h2>
                        <div id="collapse-{{ $s['id'] }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                             aria-labelledby="heading-{{ $s['id'] }}" data-bs-parent="#policyAccordion">
                            <div class="accordion-body">
                                @foreach ($s['paragraphs'] as $p)
                                    <p class="text-aroma-muted {{ $loop->last && !isset($s['cta']) ? 'mb-0' : 'mb-3' }}" style="line-height:1.9">{{ $p }}</p>
                                @endforeach
                                @isset($s['cta'])
                                    <a href="{{ route($s['cta']['route']) }}" class="fw-semibold">{{ $s['cta']['label'] }}</a>
                                @endisset
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Contact strip --}}
            <div class="aroma-trust p-4 text-center mt-4">
                <p class="mb-3">{{ $isAr ? 'هل لديك سؤال حول أي من هذه السياسات؟' : 'Have a question about any of these policies?' }}</p>
                <a href="{{ route('contact') }}" class="btn btn-aroma">
                    <i class="bi bi-envelope me-2"></i>{{ $isAr ? 'تواصل معنا' : 'Contact Us' }}
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Deep links like /privacy-policy#complaints (used by the Contact page)
    // should open that accordion section and scroll to it.
    (function () {
        var hash = window.location.hash.replace('#', '');
        if (!hash) { return; }

        var item = document.getElementById(hash);
        if (!item) { return; }

        var collapseEl = item.querySelector('.accordion-collapse');
        var buttonEl = item.querySelector('.accordion-button');

        if (collapseEl && window.bootstrap) {
            bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
        }
        if (buttonEl) {
            buttonEl.classList.remove('collapsed');
            buttonEl.setAttribute('aria-expanded', 'true');
        }

        setTimeout(function () {
            item.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 150);
    })();
</script>
@endpush
@endsection
