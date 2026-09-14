<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    private ReviewService $reviews;

    public function __construct(ReviewService $reviews)
    {
        $this->reviews = $reviews;
    }

    public function index(Request $request): View
    {
        $query = ProductReview::query()->with(['product', 'user']);

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('body', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%"));
        }
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->query('rating'));
        }
        if ($request->boolean('reported')) {
            $query->where('reported_count', '>', 0);
        }

        return view('admin.reviews.index', [
            'reviews' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'status', 'rating', 'reported']),
        ]);
    }

    public function approve(ProductReview $review): RedirectResponse
    {
        $this->reviews->approve($review);

        return back()->with('status', __('admin.reviews.approved'));
    }

    public function reject(ProductReview $review): RedirectResponse
    {
        $this->reviews->reject($review);

        return back()->with('status', __('admin.reviews.rejected'));
    }

    public function hide(ProductReview $review): RedirectResponse
    {
        $this->reviews->hide($review);

        return back()->with('status', __('admin.reviews.hidden'));
    }

    public function destroy(ProductReview $review): RedirectResponse
    {
        $this->reviews->delete($review);

        return back()->with('status', __('admin.reviews.deleted'));
    }
}
