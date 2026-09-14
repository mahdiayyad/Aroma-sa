<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    private ReviewService $reviews;

    public function __construct(ReviewService $reviews)
    {
        $this->reviews = $reviews;
    }

    public function store(ReviewRequest $request, Product $product): RedirectResponse
    {
        $result = $this->reviews->submit($request->user(), $product, $request->validated());

        if (! $result['success']) {
            return back()->withErrors(['review' => __('reviews.errors.'.$result['error'])]);
        }

        return back()->with('status', __('reviews.submitted'));
    }
}
