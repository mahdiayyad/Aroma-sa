{{-- Review list — empty state, cards, pagination. Always newest-first, no
     sort/filter controls (kept deliberately simple). --}}
@if ($reviews->isEmpty())
    <div class="aroma-trust text-center py-5">
        <i class="bi bi-chat-square-text fs-1 text-aroma-light-brown"></i>
        <p class="text-aroma-muted small mt-3 mb-0">{{ __('reviews.no_reviews') }}</p>
    </div>
@else
    <div class="aroma-review-list">
        @foreach ($reviews as $review)
            @include('catalog.partials.reviews.item', ['review' => $review])
        @endforeach
    </div>

    <div class="mt-4">{{ $reviews->links() }}</div>
@endif
