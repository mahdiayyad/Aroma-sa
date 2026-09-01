@php($locale = app()->getLocale())
@php($waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp')))

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
                    @if (config('aroma.contact.instagram'))
                        <a href="{{ config('aroma.contact.instagram') }}" target="_blank" rel="noopener"><i class="bi bi-instagram"></i></a>
                    @else
                        <a href="#"><i class="bi bi-instagram"></i></a>
                    @endif
                    @if (config('aroma.contact.tiktok'))
                        <a href="{{ config('aroma.contact.tiktok') }}" target="_blank" rel="noopener"><i class="bi bi-tiktok"></i></a>
                    @else
                        <a href="#"><i class="bi bi-tiktok"></i></a>
                    @endif
                    @if ($waDigits !== '')
                        <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a>
                    @else
                        <a href="#"><i class="bi bi-whatsapp"></i></a>
                    @endif
                    @if (config('aroma.contact.snapchat'))
                        <a href="{{ config('aroma.contact.snapchat') }}" target="_blank" rel="noopener"><i class="bi bi-snapchat"></i></a>
                    @else
                        <a href="#"><i class="bi bi-snapchat"></i></a>
                    @endif
                    @if (config('aroma.contact.facebook'))
                        <a href="{{ config('aroma.contact.facebook') }}" target="_blank" rel="noopener"><i class="bi bi-facebook"></i></a>
                    @else
                        <a href="#"><i class="bi bi-facebook"></i></a>
                    @endif
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3">{{ __('storefront.footer.about') }}</h6>
                <ul class="list-unstyled small aroma-footer-links">
                    <li class="mb-2"><a href="{{ route('about') }}">{{ __('storefront.footer.about') }}</a></li>
                    <li class="mb-2"><a href="{{ route('contact') }}">{{ __('storefront.footer.contact') }}</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3">{{ __('storefront.footer.help') }}</h6>
                <ul class="list-unstyled small aroma-footer-links">
                    <li class="mb-2"><a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">{{ __('storefront.nav.account') }}</a></li>
                    <li class="mb-2"><a href="{{ route('privacy-policy') }}">{{ __('storefront.footer.policies') }}</a></li>
                    <li class="mb-2"><a href="{{ route('terms') }}">{{ __('storefront.footer.terms') }}</a></li>
                    <li class="mb-2"><a href="{{ route('guides.index') }}">{{ __('storefront.footer.guides') }}</a></li>
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
                    <span class="aroma-footer-payment-chip"><x-payment-icon method="tabby" /></span>
                    <span class="aroma-footer-payment-chip"><x-payment-icon method="tamara" /></span>
                </div>
            </div>
        </div>

        <hr class="mt-4">
        <div class="d-flex flex-wrap justify-content-between small">
            <span><span class="footer-rights">©</span> {{ date('Y') }} {{ $brand['name'] }}. {{ __('storefront.footer.rights') }}</span>
            <span class="aroma-script">{{ __('storefront.footer.tagline') }}</span>
        </div>
    </div>

    {{-- Closing ornamental band — the same botanical motif used sitewide,
         beige-on-burgundy, full-bleed edge to edge as a quiet signature at
         the very bottom of the page. Decorative only. --}}
</footer>
