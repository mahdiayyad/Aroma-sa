@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';
@endphp

@section('title', ($isAr ? 'من نحن' : 'About Us').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'أروما علامة سعودية تنتقي العطور والزهور ومنتجات الجمال لتوقظ الأناقة وتحتفي بتفرّدك.'
    : 'Aroma is a Saudi brand curating fragrances, florals and beauty — awakening elegance, celebrating individuality.')

@section('content')
@php
    $about = $isAr
        ? 'أروما علامة تجارية سعودية تنتقي بعناية أرقى العطور الفاخرة، وتنسيقات الزهور، ومنتجات الجمال التي توقظ الأناقة وتحتفي بتفرّد كل امرأة، من خلال أحدث ما وصلت إليه صناعة العطور والعناية بالبشرة والمكياج والإكسسوارات في المنطقة. نسعى إلى إثراء الحياة عبر مزج متناغم بين العطور والزهور والجمال، بما يمنح الأفراد القدرة على التعبير عن مشاعرهم وتحويلها، ليختبروا ارتباطاً عاطفياً عميقاً ونمواً شخصياً حقيقياً.'
        : 'Aroma is a Saudi based brand that curates captivating fragrances, floral arrangements, and beauty products that awaken elegance and celebrate individuality with the latest fragrance, skincare, makeup, and accessories in the region. We are enriching lives by creating a harmonious fusion of scents, flowers, and beauty, nurturing the ability to express and transform feelings, allowing individuals to experience a profound emotional connection and personal growth.';

    $mission = $isAr
        ? 'في أروما، نختار بعناية فائقة العطور الآسرة وتنسيقات الزهور ومنتجات الجمال التي توقظ الأناقة وتحتفي بالتفرّد. لتكن أروما بوابتك إلى عالم استثنائي، حيث تُلهمك العطور، وتسحرك الأزهار، وتزدهر أناقتك.'
        : 'At Aroma, we curate captivating fragrances, floral arrangements, and beauty products that awaken your elegance and celebrate your individuality. Let Aroma be your gateway to an exquisite world where scents inspire, blooms enchant, and your style flourishes.';

    $vision = $isAr
        ? 'تكمن رؤيتنا في إثراء الحياة من خلال مزج متناغم بين العطور والزهور والجمال، بما ينمّي القدرة على التعبير عن المشاعر وتحويلها، ليختبر الأفراد ارتباطاً عاطفياً عميقاً ونمواً شخصياً. تضع أروما معياراً يُحتذى به كعلامة تجارية موثوقة، مرادفة للرقي والمتعة الحسية والجمال الدائم.'
        : 'Our vision is to enrich lives by creating a harmonious fusion of scents, flowers, and beauty, nurturing the ability to express and transform feelings, allowing individuals to experience a profound emotional connection and personal growth. Aroma sets the standard as a trusted brand, synonymous with sophistication, sensory delight, and lasting beauty.';

    $values = $isAr ? [
        ['bi-heart', 'الارتباط العاطفي', 'ندرك تأثير العطور والزهور والجمال في إثارة المشاعر وخلق ذكريات لا تُنسى. نسعى لبناء ارتباط عاطفي بين منتجاتنا وعملائنا، يتيح لهم التعبير عن مشاعرهم وتحويلها من خلال ما نقدمه.'],
        ['bi-gem', 'الجودة والتميز', 'نلتزم بتقديم ما لا يقل عن منتجات وخدمات استثنائية. نستقي عطورنا من أرقى الماركات العالمية، ونختار بعناية أنضر وأجمل الزهور، لضمان أن يترك كل توصيل انطباعاً لا يُنسى.'],
        ['bi-shield-check', 'الثقة والنزاهة', 'نقدّر الثقة التي يمنحنا إياها عملاؤنا. نعمل بشفافية وصدق ونزاهة في جميع جوانب أعمالنا، ونحرص على بناء علاقات طويلة الأمد قائمة على الثقة والالتزام بأعلى المعايير الأخلاقية.'],
        ['bi-lightbulb', 'الإبداع والابتكار', 'نبحث باستمرار عن طرق جديدة ومبتكرة لإثراء تجربة عملائنا. من مجموعات الهدايا المنسقة إلى التنسيقات المخصصة، نتبنى الإبداع لنضمن لعملائنا تعبيراً أصيلاً ومميزاً.'],
    ] : [
        ['bi-heart', 'Emotional Connection', 'We understand the power of scents, flowers, and beauty in evoking emotions and creating lasting memories. We aim to forge an emotional connection between our products and our customers, allowing them to express and transform their feelings.'],
        ['bi-gem', 'Quality & Excellence', 'We are dedicated to delivering nothing less than outstanding products and services. We source our perfumes from renowned brands and carefully select the freshest, most beautiful flowers for every delivery.'],
        ['bi-shield-check', 'Trust & Integrity', 'We value the trust placed in us by our customers. We operate with transparency, honesty, and integrity in all aspects of our business — building long-lasting relationships and upholding the highest ethical standards.'],
        ['bi-lightbulb', 'Creativity & Innovation', "We constantly seek new and imaginative ways to enhance our customers' experiences. From curated gift sets to customized arrangements, we embrace creativity to help you express what truly matters."],
    ];
@endphp

{{-- Hero --}}
<section class="container mt-4">
    <div class="aroma-hero text-center aroma-animate-in">
        <div class="aroma-hero-tagline mb-2">{{ $brand['tagline'] ?? 'Awaken your Senses' }}</div>
        <h1 class="mb-3">{{ $isAr ? 'من نحن' : 'About Aroma' }}</h1>
        <p class="lead aroma-hero-lead mb-0">
            {{ $isAr ? 'علامة سعودية توقظ حواسك وتحتفي بأناقتك في كل تفصيلة.' : 'A Saudi brand awakening your senses, one exquisite detail at a time.' }}
        </p>
    </div>
</section>

{{-- Who we are --}}
<section class="container aroma-section">
    <div class="row align-items-center g-5">
        <div class="col-lg-6">
            <span class="aroma-eyebrow d-block mb-2">{{ $isAr ? 'قصتنا' : 'Our Story' }}</span>
            <h2 class="aroma-section-title mb-4">{{ $isAr ? 'من نحن' : 'Who We Are' }}</h2>
            <p class="text-aroma-muted fs-5" style="line-height:1.9">{{ $about }}</p>
        </div>
        <div class="col-lg-6">
            <div class="aroma-trust p-4 p-lg-5 text-center">
                <i class="bi bi-flower1 aroma-icon-xl text-aroma-light-brown d-block mb-3"></i>
                <div class="aroma-script fs-2 text-aroma-brown">{{ $brand['tagline'] ?? 'Awaken your Senses' }}</div>
            </div>
        </div>
    </div>
</section>

{{-- Mission & Vision --}}
<section class="container aroma-section">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="aroma-card h-100 p-4 p-lg-5">
                <i class="bi bi-compass fs-1 text-aroma-brown mb-3 d-block"></i>
                <h3 class="mb-3">{{ $isAr ? 'رسالتنا' : 'Our Mission' }}</h3>
                <p class="text-aroma-muted mb-0" style="line-height:1.9">{{ $mission }}</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="aroma-card h-100 p-4 p-lg-5">
                <i class="bi bi-stars fs-1 text-aroma-brown mb-3 d-block"></i>
                <h3 class="mb-3">{{ $isAr ? 'رؤيتنا' : 'Our Vision' }}</h3>
                <p class="text-aroma-muted mb-0" style="line-height:1.9">{{ $vision }}</p>
            </div>
        </div>
    </div>
</section>

{{-- Brand values --}}
<section class="container aroma-section">
    <div class="text-center mb-5">
        <span class="aroma-eyebrow d-block mb-2">{{ $isAr ? 'ما يميزنا' : 'What Sets Us Apart' }}</span>
        <h2 class="aroma-section-title d-inline-block">{{ $isAr ? 'قيمنا' : 'Our Values' }}</h2>
    </div>
    <div class="row g-4">
        @foreach ($values as [$icon, $title, $body])
            <div class="col-md-6 col-lg-3">
                <div class="aroma-card h-100 p-4 text-center">
                    <i class="bi {{ $icon }} fs-1 text-aroma-brown mb-3 d-block"></i>
                    <h5 class="mb-2">{{ $title }}</h5>
                    <p class="text-aroma-muted small mb-0">{{ $body }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- CTA --}}
<section class="container aroma-section">
    <div class="aroma-newsletter text-center p-5">
        <h2 class="mb-2">{{ $isAr ? 'اكتشف مجموعتنا' : 'Discover the Collection' }}</h2>
        <p class="mb-4">{{ $isAr ? 'عبايات مصممة بعناية لتحتفي بالأناقة في كل مناسبة.' : 'Abayas crafted with care, for every moment worth celebrating.' }}</p>
        <a href="{{ route('home', $locale) }}" class="btn btn-aroma-light btn-lg px-4">
            {{ $isAr ? 'تسوّق الآن' : 'Shop Now' }}
        </a>
    </div>
</section>
@endsection
