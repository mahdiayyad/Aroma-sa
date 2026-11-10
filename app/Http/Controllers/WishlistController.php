<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\TrackingService;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index()
    {
        $products = auth()->user()
            ->wishlistedProducts()
            ->with(['images', 'brand'])
            ->withCount(['options as required_options_count' => fn ($q) => $q->where('is_required', true)])
            ->latest('wishlists.created_at')
            ->paginate(12);

        return view('account.wishlist', ['products' => $products]);
    }

    /** Add/remove a product from the wishlist (idempotent toggle). */
    public function toggle(Request $request, Product $product, TrackingService $tracking)
    {
        $user = $request->user();

        $existing = $user->wishlistItems()->where('product_id', $product->id)->first();

        if ($existing) {
            $existing->delete();
            $active  = false;
            $message = __('account.flash.wishlist_removed');
        } else {
            $user->wishlistItems()->create(['product_id' => $product->id]);
            $active  = true;
            $message = __('account.flash.wishlist_added');
        }

        // Removing from the wishlist has no standard event, so only adds are tracked.
        $trackingEvent = $active ? $tracking->addToWishlist($product) : null;

        // Live toggle: the storefront posts via fetch and updates the heart +
        // header count without a reload. Non-JS clients still get the redirect.
        if ($request->expectsJson()) {
            return response()->json([
                'active'  => $active,
                'count'   => $user->wishlistItems()->count(),
                'message' => $message,
                'tracking' => $tracking->forJson([$trackingEvent]),
            ]);
        }

        $tracking->flash($trackingEvent);

        return back()->with('status', $message);
    }
}
