{{-- Packaging & thank-you card (Item 11) — reassures shoppers/merchants that
     every order arrives in premium, on-brand packaging. Uses the real assets
     extracted from the brand guidelines. --}}
<section class="aroma-packaging" aria-label="{{ __('storefront.packaging.title') }}">
    <div class="text-center mb-4">
        <span class="aroma-eyebrow d-block mb-1">{{ __('storefront.packaging.eyebrow') }}</span>
        <h2 class="aroma-section-title d-inline-block">{{ __('storefront.packaging.title') }}</h2>
        <p class="text-aroma-muted mx-auto mb-0" style="max-width:600px">{{ __('storefront.packaging.subtitle') }}</p>
    </div>

    <div class="row g-3">
        @foreach ([['packaging-gift.jpg', 'gift'], ['packaging-bags.jpg', 'bags'], ['thank-you-card.jpg', 'card']] as [$img, $key])
            <div class="col-md-4">
                <figure class="aroma-packaging-item">
                    <img src="{{ asset('images/brand/'.$img) }}" alt="{{ __('storefront.packaging.'.$key) }}" loading="lazy">
                    <figcaption>{{ __('storefront.packaging.'.$key) }}</figcaption>
                </figure>
            </div>
        @endforeach
    </div>
</section>
