@php
    $locale = app()->getLocale();
    $waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp'));
    $commercialRegistration = (string) config('aroma.contact.commercial_registration');
    $vatNumber = (string) config('aroma.receipts.vat_number');

    // Icon order is the display order; a network with no URL drops out
    // entirely rather than rendering a link that goes nowhere.
    $socialLinks = array_filter([
        'instagram' => config('aroma.contact.instagram'),
        'tiktok'    => config('aroma.contact.tiktok'),
        'whatsapp'  => $waDigits !== '' ? 'https://wa.me/'.$waDigits : '',
        'snapchat'  => config('aroma.contact.snapchat'),
        'facebook'  => config('aroma.contact.facebook'),
    ]);
@endphp

<footer class="aroma-footer mt-5 pt-5 pb-4">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="aroma-footer-brand mb-2">{{ $brand['name'] }}</div>
                <p class="mb-3 aroma-footer-tagline">{{ __('storefront.hero.subtitle') }}</p>
                <a href="mailto:{{ config('aroma.contact.email') }}" class="d-inline-flex align-items-center gap-2 mb-3 small aroma-footer-email">
                    <i class="bi bi-envelope"></i>{{ config('aroma.contact.email') }}
                </a>
                <div class="d-flex gap-3 fs-5 aroma-footer-social">
                    @foreach ($socialLinks as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><i class="bi bi-{{ $network }}"></i></a>
                    @endforeach
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3">{{ __('storefront.footer.about') }}</h6>
                <ul class="list-unstyled small aroma-footer-links">
                    <li class="mb-2"><a href="{{ route('about') }}">{{ __('storefront.footer.about') }}</a></li>
                    <li class="mb-2"><a href="{{ route('contact') }}">{{ __('storefront.footer.contact') }}</a></li>
                    @if ($hasBlog)
                        <li class="mb-2"><a href="{{ route('blog.index', $locale) }}">{{ __('storefront.footer.blog') }}</a></li>
                    @endif
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3">{{ __('storefront.footer.help') }}</h6>
                <ul class="list-unstyled small aroma-footer-links">
                    <li class="mb-2"><a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">{{ __('storefront.nav.account') }}</a></li>
                    <li class="mb-2"><a href="{{ route('privacy-policy') }}">{{ __('storefront.footer.policies') }}</a></li>
                    <li class="mb-2"><a href="{{ route('terms') }}">{{ __('storefront.footer.terms') }}</a></li>
                    <li class="mb-2"><a href="{{ route('guides.index') }}">{{ __('storefront.footer.guides') }}</a></li>
                    <li class="mb-2"><a href="{{ route('faq') }}">{{ __('storefront.footer.faq') }}</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6 class="text-uppercase mb-3">{{ __('storefront.newsletter.title') }}</h6>
                <form class="d-flex gap-2" action="#" method="post">
                    @csrf
                    <input type="email" class="form-control" placeholder="{{ __('storefront.newsletter.placeholder') }}">
                    <button class="btn btn-aroma-light" type="submit">{{ __('storefront.newsletter.cta') }}</button>
                </form>
                {{-- Real brand-colored marks (see components/payment-icon.blade.php),
                     not generic Bootstrap glyphs — each sits on its own small
                     light chip since several of these marks assume a light
                     card background, which the dark footer isn't. --}}
                <div class="d-flex flex-wrap gap-2 mt-3 aroma-footer-payment-icons">
                    <span class="aroma-footer-payment-chip"><x-payment-icon method="mada" /></span>
                    <span class="aroma-footer-payment-chip"><x-payment-icon method="visa" /></span>
                    <span class="aroma-footer-payment-chip"><x-payment-icon method="mastercard" /></span>
                    <span class="aroma-footer-payment-chip"><x-payment-icon method="applepay" /></span>
                    <span class="aroma-footer-payment-chip"><x-payment-icon method="tamara" /></span>
                </div>
            </div>
        </div>

        <hr class="mt-4">
        <div class="d-flex flex-wrap justify-content-between small">
            <span><span class="footer-rights">©</span> {{ date('Y') }} {{ $brand['name'] }}. {{ __('storefront.footer.rights') }}</span>
            <span class="aroma-script">{{ __('storefront.footer.tagline') }}</span>
        </div>
        @if ($commercialRegistration !== '' || $vatNumber !== '')
            <div class="d-flex flex-wrap gap-3 small mt-2">
                @if ($commercialRegistration !== '')
                    <span>{{ __('storefront.footer.commercial_registration', ['number' => $commercialRegistration]) }}</span>
                @endif
                @if ($vatNumber !== '')
                    <span>{{ __('storefront.footer.vat_number', ['number' => $vatNumber]) }}</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Closing ornamental band — the same botanical motif used sitewide,
         beige-on-burgundy, full-bleed edge to edge as a quiet signature at
         the very bottom of the page. Decorative only. --}}
</footer>
