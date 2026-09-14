{{-- Rating summary — average + stars + total count. Deliberately just this:
     no per-star distribution bars, no filter links (kept simple/minimal). --}}
<div class="aroma-review-summary text-center">
    <div class="aroma-review-avg">{{ number_format($avgRating, 1) }}</div>
    <x-star-rating :rating="$avgRating" />
    <p class="text-aroma-muted small mt-2 mb-0">
        {{ $totalReviews === 1 ? __('reviews.based_on_one') : __('reviews.based_on', ['count' => $totalReviews]) }}
    </p>
</div>
