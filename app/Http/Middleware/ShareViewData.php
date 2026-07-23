<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CartService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Shares per-request UI state with every view: the cart item count (for the
 * header badge) and the current user's wishlisted product ids (so product cards
 * render the correct heart state without an N+1 query per card).
 *
 * Runs inside the web group after the session has started.
 */
class ShareViewData
{
    /** @var CartService */
    private $cart;

    public function __construct(CartService $cart)
    {
        $this->cart = $cart;
    }

    public function handle(Request $request, Closure $next)
    {
        View::share('cartCount', $this->cart->count());

        $wishlistIds = [];
        if ($request->user()) {
            $wishlistIds = $request->user()->wishlistItems()->pluck('product_id')->all();
        }
        View::share('wishlistIds', $wishlistIds);

        return $next($request);
    }
}
