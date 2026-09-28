@extends('layouts.app')

@php
    $locale = $locale ?? app()->getLocale();
    $isAr = $locale === 'ar';
@endphp

@section('title', ($isAr ? 'من نحن' : 'About Us').' — '.$brand['name'])
@section('meta_description', $isAr
    ? 'أروما علامة سعودية تصمم العبايات بعناية فائقة، تحتفي بالأناقة والحشمة وتفرّد كل امرأة.'
    : 'Aroma is a Saudi brand crafting abayas with meticulous care — celebrating elegance, modesty, and individuality.')

@section('content')
@php
    $about = $isAr
        ? 'أروما علامة تجارية سعودية تصمم وتنتقي بعناية أرقى العبايات التي تحتفي بالأناقة والحشمة وتفرّد كل امرأة، معتمدة على أجود الأقمشة وأدق تفاصيل الخياطة. نسعى إلى إثراء حياة عميلاتنا من خلال عبايات تجمع بين التراث السعودي والتصميم العصري، بما يمنحهنّ الثقة والراحة في كل مناسبة.'
        : 'Aroma is a Saudi based brand that designs and curates exquisite abayas celebrating elegance, modesty, and every woman\'s individuality — crafted from the finest fabrics with meticulous attention to tailoring. We are enriching our customers\' lives with abayas that blend Saudi heritage with contemporary design, giving them confidence and comfort for every occasion.';

    $mission = $isAr
        ? 'في أروما، نصمم بعناية فائقة عبايات تجمع بين الأناقة والحشمة والراحة. لتكن أروما بوابتك إلى خزانة أنيقة، حيث تلتقي جودة الأقمشة بدقة التفصيل لتشعري بالثقة في كل خطوة.'
        : 'At Aroma, we carefully design abayas that bring together elegance, modesty, and comfort. Let Aroma be your gateway to an effortlessly elegant wardrobe, where fine fabrics meet precise tailoring so you feel confident in every step.';

    $vision = $isAr
        ? 'تكمن رؤيتنا في إثراء خزانة كل امرأة سعودية من خلال عبايات تعكس هويتها وتحتفي بحشمتها وأناقتها. تضع أروما معياراً يُحتذى به كعلامة تجارية موثوقة، مرادفة للجودة والحرفية والأناقة الدائمة.'
        : 'Our vision is to enrich every woman\'s wardrobe with abayas that reflect her identity and celebrate both her modesty and her elegance. Aroma sets the standard as a trusted brand, synonymous with quality, craftsmanship, and lasting style.';

    $values = $isAr ? [
        ['bi-heart', 'الارتباط العاطفي', 'ندرك أهمية العباية في حياة المرأة السعودية كقطعة تعكس هويتها وثقتها. نسعى لبناء ارتباط عاطفي بين تصاميمنا وعميلاتنا، بما يتيح لهنّ التعبير عن أناقتهنّ الخاصة.'],
        ['bi-gem', 'الجودة والتميز', 'نلتزم بتقديم ما لا يقل عن منتجات استثنائية. نستقي أقمشتنا من أرقى الموردين، ونحرص على أدق تفاصيل الخياطة والتشطيب، لضمان أن تترك كل عباية انطباعاً لا يُنسى.'],
        ['bi-shield-check', 'الثقة والنزاهة', 'نقدّر الثقة التي يمنحنا إياها عملاؤنا. نعمل بشفافية وصدق ونزاهة في جميع جوانب أعمالنا، ونحرص على بناء علاقات طويلة الأمد قائمة على الثقة والالتزام بأعلى المعايير الأخلاقية.'],
        ['bi-lightbulb', 'الإبداع والابتكار', 'من التصاميم الموسمية إلى القطع المفصّلة بحسب الطلب، نتبنى الإبداع لنضمن لعميلاتنا تعبيراً أصيلاً ومميزاً عن أناقتهنّ الخاصة.'],
    ] : [
        ['bi-heart', 'Emotional Connection', 'We understand how meaningful an abaya is to a woman\'s identity and confidence. We aim to forge an emotional connection between our designs and our customers, letting every piece reflect her own sense of style.'],
        ['bi-gem', 'Quality & Excellence', 'We are dedicated to delivering nothing less than outstanding products. We source our fabrics from trusted, high-quality suppliers and pay close attention to every stitch and finish, ensuring every abaya leaves a lasting impression.'],
        ['bi-shield-check', 'Trust & Integrity', 'We value the trust placed in us by our customers. We operate with transparency, honesty, and integrity in all aspects of our business — building long-lasting relationships and upholding the highest ethical standards.'],
        ['bi-lightbulb', 'Creativity & Innovation', 'From seasonal collections to made-to-order pieces, we embrace creativity to help every customer express her own distinct sense of style.'],
    ];
@endphp

{{-- Hero --}}
<section class="container mt-4">
    <div class="aroma-hero text-center aroma-animate-in">
        <div class="aroma-hero-tagline mb-2">{{ $brand['tagline'] ?? 'Awaken your Senses' }}</div>
        <h1 class="mb-3">{{ $isAr ? 'من نحن' : 'About Aroma' }}</h1>
        <p class="lead aroma-hero-lead mb-0">
            {{ $isAr ? 'علامة سعودية تحتفي بالأناقة والحشمة في كل تفصيلة.' : 'A Saudi brand celebrating elegance and modesty in every detail.' }}
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
