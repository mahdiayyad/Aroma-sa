{{-- Individual review card — name, rating, comment, date (+ verified badge). --}}
<div class="aroma-review-card">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="aroma-avatar">{{ $review->user->initials() }}</span>
            <div>
                <div class="fw-semibold">{{ $review->user->name }}</div>
                <div class="small text-aroma-muted">{{ $review->created_at->translatedFormat('j M Y') }}</div>
            </div>
        </div>
        @if ($review->is_verified_purchase)
            <span class="aroma-badge-status aroma-badge-success"><i class="bi bi-patch-check-fill me-1"></i>{{ __('reviews.verified_purchase') }}</span>
        @endif
    </div>

    <x-star-rating :rating="$review->rating" />

    <p class="mb-0 mt-2 text-aroma-muted">{{ $review->body }}</p>
</div>
