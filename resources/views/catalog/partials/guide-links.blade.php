{{-- Customer Guide links — abaya products only (see the @if in product.blade.php).
     Modeled on payment-methods.blade.php's structure: small section, icon
     title, list of items. Styled with existing utility classes only, so the
     product page never needs to load guides.css. --}}
<section class="aroma-payments" aria-labelledby="aromaGuideLinksTitle">
    <h2 class="aroma-payments-title" id="aromaGuideLinksTitle">
        <i class="bi bi-info-circle" aria-hidden="true"></i>{{ __('guides.product_links.title') }}
    </h2>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('guides.sizing') }}" class="btn btn-aroma-outline btn-sm">
            <i class="bi bi-rulers me-1"></i>{{ __('guides.product_links.sizing') }}
        </a>
        <a href="{{ route('guides.fit') }}" class="btn btn-aroma-outline btn-sm">
            <i class="bi bi-gem me-1"></i>{{ __('guides.product_links.fit') }}
        </a>
        <a href="{{ route('guides.care') }}" class="btn btn-aroma-outline btn-sm">
            <i class="bi bi-droplet me-1"></i>{{ __('guides.product_links.care') }}
        </a>
    </div>
</section>
