@php($locale = app()->getLocale())
@php($waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp')))

<footer class="aroma-footer mt-5 pt-5 pb-4">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="aroma-footer-brand mb-2">{{ $brand['name'] }}</div>
                <p class="mb-3">{{ __('storefront.hero.subtitle') }}</p>
                <a href="mailto:{{ config('aroma.contact.email') }}" class="d-inline-flex align-items-center gap-2 mb-3 small">
                    <i class="bi bi-envelope"></i>{{ config('aroma.contact.email') }}
                </a>
                <div class="d-flex gap-3 fs-5">
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-tiktok"></i></a>
                    @if ($waDigits !== '')
                        <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a>
                    @else
                        <a href="#"><i class="bi bi-whatsapp"></i></a>
                    @endif
                    <a href="#"><i class="bi bi-snapchat"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3">{{ __('storefront.footer.about') }}</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="{{ route('about') }}">{{ __('storefront.footer.about') }}</a></li>
                    <li class="mb-2"><a href="{{ route('contact') }}">{{ __('storefront.footer.contact') }}</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3">{{ __('storefront.footer.help') }}</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">{{ __('storefront.nav.account') }}</a></li>
                    <li class="mb-2"><a href="{{ route('privacy-policy') }}">{{ __('storefront.footer.policies') }}</a></li>
                    <li class="mb-2"><a href="{{ route('terms') }}">{{ __('storefront.footer.terms') }}</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6 class="text-uppercase mb-3">{{ __('storefront.newsletter.title') }}</h6>
                <form class="d-flex gap-2" action="#" method="post">
                    @csrf
                    <input type="email" class="form-control" placeholder="{{ __('storefront.newsletter.placeholder') }}">
                    <button class="btn btn-aroma" type="submit">{{ __('storefront.newsletter.cta') }}</button>
                </form>
                <div class="d-flex gap-2 mt-3 fs-4 opacity-75">
                    <i class="bi bi-credit-card-2-front" data-bs-toggle="tooltip" data-bs-placement="top" title="Mada / Visa / Mastercard"></i>
                    <i class="bi bi-apple" data-bs-toggle="tooltip" data-bs-placement="top" title="Apple Pay"></i>
                    <i class="bi bi-wallet2" data-bs-toggle="tooltip" data-bs-placement="top" title="Tabby / Tamara"></i>
                </div>
            </div>
        </div>

        <hr class="mt-4">
        <div class="d-flex flex-wrap justify-content-between small">
            <span><span class="footer-rights">©</span> {{ date('Y') }} {{ $brand['name'] }}. {{ __('storefront.footer.rights') }}</span>
            <span class="aroma-script">{{ __('storefront.footer.tagline') }}</span>
        </div>
    </div>
</footer>
