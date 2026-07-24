<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Support\Formatting\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /** @var CartService */
    private $cart;

    public function __construct(CartService $cart)
    {
        $this->cart = $cart;
    }

    public function index()
    {
        return view('cart.index', [
            'rows'     => $this->cart->rows(),
            'subtotal' => $this->cart->subtotalLabel(),
            'count'    => $this->cart->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'qty'        => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $this->cart->add(
            (int) $data['product_id'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            (int) ($data['qty'] ?? 1)
        );

        // Live add-to-cart: the storefront submits these via fetch and expects
        // JSON so it can show a toast and update the header count without a
        // full page reload. Non-JS clients still get the redirect + flash.
        if ($request->expectsJson()) {
            return response()->json([
                'count'   => $this->cart->count(),
                'message' => __('cart.flash.added'),
            ]);
        }

        return back()->with('status', __('cart.flash.added'));
    }

    public function update(Request $request, string $rowId)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:99']]);

        $this->cart->update($rowId, (int) $data['qty']);

        // Live cart: the cart page patches quantities via fetch and expects JSON
        // so the line total, subtotal and header count update without a reload.
        if ($request->expectsJson()) {
            $row = $this->cart->rows()[$rowId] ?? null;

            return response()->json([
                'count'      => $this->cart->count(),
                'removed'    => $row === null,
                'qty'        => $row['qty'] ?? 0,
                'line_total' => $row ? Money::format($row['unit_price'] * $row['qty']) : null,
                'subtotal'   => $this->cart->subtotalLabel(),
            ]);
        }

        return back()->with('status', __('cart.flash.updated'));
    }

    public function remove(string $rowId): RedirectResponse
    {
        $this->cart->remove($rowId);

        return back()->with('status', __('cart.flash.removed'));
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return back()->with('status', __('cart.flash.cleared'));
    }
}
