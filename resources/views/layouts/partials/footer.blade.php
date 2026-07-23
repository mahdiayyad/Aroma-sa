@php($locale = app()->getLocale())

<footer class="aroma-footer mt-5 pt-5 pb-4">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="aroma-footer-brand mb-2">{{ $brand['name'] }}</div>
                <p class="mb-3">{{ __('storefront.hero.subtitle') }}</p>
                <div class="d-flex gap-3 fs-5">
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-tiktok"></i></a>
                    <a href="#"><i class="bi bi-whatsapp"></i></a>
                    <a href="#"><i class="bi bi-snapchat"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3" style="color:var(--aroma-skin)">{{ __('storefront.footer.about') }}</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="#">{{ __('storefront.sections.brands') }}</a></li>
                    <li class="mb-2"><a href="#">{{ __('storefront.footer.contact') }}</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase mb-3" style="color:var(--aroma-skin)">{{ __('storefront.footer.help') }}</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="#">{{ __('storefront.nav.account') }}</a></li>
                    <li class="mb-2"><a href="#">{{ __('storefront.footer.policies') }}</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6 class="text-uppercase mb-3" style="color:var(--aroma-skin)">{{ __('storefront.newsletter.title') }}</h6>
                <form class="d-flex gap-2" action="#" method="post">
                    @csrf
                    <input type="email" class="form-control" placeholder="{{ __('storefront.newsletter.placeholder') }}">
                    <button class="btn btn-aroma" type="submit">{{ __('storefront.newsletter.cta') }}</button>
                </form>
                <div class="d-flex gap-2 mt-3 fs-4 opacity-75">
                    <i class="bi bi-credit-card-2-front" title="Mada / Visa / Mastercard"></i>
                    <i class="bi bi-apple" title="Apple Pay"></i>
                    <i class="bi bi-wallet2" title="Tabby / Tamara"></i>
                </div>
            </div>
        </div>

        <hr class="mt-4" style="border-color:rgba(255,243,226,.15)">
        <div class="d-flex flex-wrap justify-content-between small">
            <span>© {{ date('Y') }} {{ $brand['name'] }}. {{ __('storefront.footer.rights') }}</span>
            <span class="aroma-script">{{ __('storefront.footer.tagline') }}</span>
        </div>
    </div>
</footer>
