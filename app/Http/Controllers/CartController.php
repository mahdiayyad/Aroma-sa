<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CartService;
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

    public function store(Request $request): RedirectResponse
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

        return back()->with('status', __('cart.flash.added'));
    }

    public function update(Request $request, string $rowId): RedirectResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:99']]);

        $this->cart->update($rowId, (int) $data['qty']);

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
