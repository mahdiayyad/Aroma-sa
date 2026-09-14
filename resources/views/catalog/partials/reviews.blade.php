@php($avgRating = (float) $product->reviews_avg_rating)
@php($totalReviews = (int) $product->reviews_count)

<section class="aroma-reviews-section mt-5" id="reviews">
    <h2 class="aroma-section-title mb-4">{{ __('reviews.title') }}</h2>

    <div class="row g-4 mb-4 align-items-stretch">
        <div class="col-lg-4">
            <div class="aroma-trust p-4 h-100 d-flex align-items-center justify-content-center">
                @include('catalog.partials.reviews.summary')
            </div>
        </div>
        <div class="col-lg-8">
            <div class="aroma-trust p-4 h-100">
                @include('catalog.partials.reviews.form')
            </div>
        </div>
    </div>

    @include('catalog.partials.reviews.list')
</section>
