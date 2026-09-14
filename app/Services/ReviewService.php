<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Support\Services\BaseService;
use Illuminate\Database\QueryException;

/**
 * Submission and moderation for product reviews — kept deliberately to just
 * these two concerns; helpful-voting/reporting were removed along with the
 * rest of the simplified review UI (the product_review_votes/reports tables
 * and models are still there, just unused, in case that's wanted back later).
 *
 * unique(user_id, product_id) on product_reviews is the real guard against
 * duplicate reviews — the check here is just for a friendly error before
 * hitting that constraint.
 *
 * Reviews are auto-approved at submission (no pre-moderation queue) — this
 * is a deliberate simplification, not an oversight: the previous
 * status='pending' default meant a freshly submitted review was invisible
 * to everyone, including its own author, until an admin acted on it, which
 * is the exact bug this fixes. Admins can still hide/reject/delete an
 * individual review after the fact via the admin panel; nothing about that
 * moderation capability changed, only the default starting state.
 *
 * products.reviews_avg_rating/reviews_count are recalculated transactionally
 * on every transition that changes whether a review counts (submit, or a
 * later moderation action), so the PDP never needs a live aggregate query.
 */
class ReviewService extends BaseService
{
    /**
     * @param array{rating:int,body:string} $data
     * @return array{success:bool,review?:ProductReview,error?:string}
     */
    public function submit(User $user, Product $product, array $data): array
    {
        if (ProductReview::withTrashed()->where('user_id', $user->id)->where('product_id', $product->id)->exists()) {
            return ['success' => false, 'error' => 'already_reviewed'];
        }

        $orderItem = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', Order::PAID_STATUSES))
            ->first();

        return $this->transaction(function () use ($user, $product, $data, $orderItem) {
            try {
                $review = ProductReview::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'order_item_id' => $orderItem ? $orderItem->id : null,
                    'rating' => $data['rating'],
                    'body' => $data['body'],
                    // Frozen at submission — stays true even if the order is
                    // later refunded/cancelled, since the purchase genuinely
                    // happened at review time.
                    'is_verified_purchase' => (bool) $orderItem,
                    'status' => ProductReview::STATUS_APPROVED,
                    'approved_at' => now(),
                ]);
            } catch (QueryException $e) {
                return ['success' => false, 'error' => 'already_reviewed'];
            }

            $this->recalculate($product);

            return ['success' => true, 'review' => $review];
        });
    }

    public function approve(ProductReview $review): void
    {
        $this->transaction(function () use ($review) {
            $review->update(['status' => ProductReview::STATUS_APPROVED, 'approved_at' => now()]);
            $this->recalculate($review->product);
        });
    }

    public function reject(ProductReview $review): void
    {
        $this->transitionAwayFromApproved($review, ProductReview::STATUS_REJECTED);
    }

    public function hide(ProductReview $review): void
    {
        $this->transitionAwayFromApproved($review, ProductReview::STATUS_HIDDEN);
    }

    /** Admin "delete inappropriate review" — soft delete, recoverable like Product's own. */
    public function delete(ProductReview $review): void
    {
        $this->transaction(function () use ($review) {
            $wasApproved = $review->status === ProductReview::STATUS_APPROVED;
            $review->delete();

            if ($wasApproved) {
                $this->recalculate($review->product);
            }
        });
    }

    private function transitionAwayFromApproved(ProductReview $review, string $newStatus): void
    {
        $this->transaction(function () use ($review, $newStatus) {
            $wasApproved = $review->status === ProductReview::STATUS_APPROVED;
            $review->update(['status' => $newStatus]);

            if ($wasApproved) {
                $this->recalculate($review->product);
            }
        });
    }

    private function recalculate(Product $product): void
    {
        $stats = $product->approvedReviews()->selectRaw('COUNT(*) as cnt, AVG(rating) as avg_rating')->first();

        $product->update([
            'reviews_count' => (int) $stats->cnt,
            'reviews_avg_rating' => $stats->cnt > 0 ? round((float) $stats->avg_rating, 2) : 0,
        ]);
    }
}
