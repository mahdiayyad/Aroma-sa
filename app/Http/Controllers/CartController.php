<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\TrackingService;
use App\Support\Formatting\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /** @var CartService */
    private $cart;

    /** @var CatalogService */
    private $catalog;

    /** @var TrackingService */
    private $tracking;

    public function __construct(CartService $cart, CatalogService $catalog, TrackingService $tracking)
    {
        $this->cart = $cart;
        $this->catalog = $catalog;
        $this->tracking = $tracking;
    }

    public function index()
    {
        $this->tracking->push($this->tracking->viewCart($this->cart->rows()));

        return view('cart.index', [
            'rows'     => $this->cart->rows(),
            'subtotal' => $this->cart->subtotalLabel(),
            'count'    => $this->cart->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'  => ['required', 'integer', 'exists:products,id'],
            'variant_id'  => ['nullable', 'integer', 'exists:product_variants,id'],
            'qty'         => ['nullable', 'integer', 'min:1', 'max:99'],
            'options'     => ['nullable', 'array'],
            'options.*'   => ['nullable', 'integer'],
        ]);

        $product = Product::active()->with('options.values')->findOrFail((int) $data['product_id']);
        $options = $this->validatedOptions($product, $data['options'] ?? []);

        $before = $this->cart->rows();
        $rowId = $this->cart->add(
            (int) $data['product_id'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            (int) ($data['qty'] ?? 1),
            $options
        );

        // What was actually added — the stock cap can make it less than asked.
        $added = $this->cart->rows()[$rowId] ?? null;
        $trackingEvent = $added
            ? $this->tracking->cartChange($added, (int) $added['qty'] - (int) ($before[$rowId]['qty'] ?? 0))
            : null;

        // Live add-to-cart: the storefront submits these via fetch and expects
        // JSON so it can open the confirmation modal and update the header count
        // without a reload. Non-JS clients still get the redirect + flash.
        if ($request->expectsJson()) {
            $row    = $this->cart->rows()[$rowId] ?? null;
            $locale = app()->getLocale();

            return response()->json([
                'count'    => $this->cart->count(),
                'message'  => __('cart.flash.added'),
                'subtotal' => $this->cart->subtotalLabel(),
                'subtotal_amount' => $this->cart->subtotal(),
                'item'     => $row ? [
                    'row_id'     => $rowId,
                    'name'       => $row['name'][$locale] ?? reset($row['name']),
                    'variant'    => $row['variant'][$locale] ?? ($row['variant'] ? reset($row['variant']) : null),
                    'options'    => collect($row['options'] ?? [])->map(function ($o) use ($locale) {
                        $delta = (float) ($o['price_delta'] ?? 0);

                        return [
                            'label' => $o['label'][$locale] ?? reset($o['label']),
                            'value' => $o['value_label'][$locale] ?? reset($o['value_label']),
                            'price' => $delta > 0 ? '+'.Money::format($delta) : null,
                        ];
                    })->values()->all(),
                    'image'      => $row['image'],
                    'qty'        => $row['qty'],
                    'unit_price' => Money::format($row['base_unit_price'] ?? $row['unit_price']),
                    'line_total' => Money::format($row['unit_price'] * $row['qty']),
                ] : null,
                // "Make your gift perfect" add-ons shown beneath the added item.
                'suggestions' => $this->suggestions($product),
                'tracking' => $this->tracking->forJson([$trackingEvent]),
            ]);
        }

        $this->tracking->flash($trackingEvent);

        return back()->with('status', __('cart.flash.added'));
    }

    public function update(Request $request, string $rowId)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:99']]);

        $before = $this->cart->rows()[$rowId] ?? null;
        $this->cart->update($rowId, (int) $data['qty']);
        $row = $this->cart->rows()[$rowId] ?? null;

        $trackingEvent = $before
            ? $this->tracking->cartChange($row ?? $before, (int) ($row['qty'] ?? 0) - (int) $before['qty'])
            : null;

        // Live cart: the cart page patches quantities via fetch and expects JSON
        // so the line total, subtotal and header count update without a reload.
        if ($request->expectsJson()) {
            return response()->json([
                'count'      => $this->cart->count(),
                'removed'    => $row === null,
                'qty'        => $row['qty'] ?? 0,
                'line_total' => $row ? Money::format($row['unit_price'] * $row['qty']) : null,
                'subtotal'   => $this->cart->subtotalLabel(),
                'subtotal_amount' => $this->cart->subtotal(),
                'tracking'   => $this->tracking->forJson([$trackingEvent]),
            ]);
        }

        $this->tracking->flash($trackingEvent);

        return back()->with('status', __('cart.flash.updated'));
    }

    public function remove(Request $request, string $rowId)
    {
        $before = $this->cart->rows()[$rowId] ?? null;
        $this->cart->remove($rowId);

        $trackingEvent = $before ? $this->tracking->cartChange($before, -(int) $before['qty']) : null;

        // Live cart: the delete button removes the row via fetch and expects
        // JSON so the row can disappear without a reload.
        if ($request->expectsJson()) {
            return response()->json([
                'count'    => $this->cart->count(),
                'message'  => __('cart.flash.removed'),
                'subtotal' => $this->cart->subtotalLabel(),
                'subtotal_amount' => $this->cart->subtotal(),
                'tracking' => $this->tracking->forJson([$trackingEvent]),
            ]);
        }

        $this->tracking->flash($trackingEvent);

        return back()->with('status', __('cart.flash.removed'));
    }

    public function clear(): RedirectResponse
    {
        $rows = $this->cart->rows();
        $this->cart->clear();

        foreach ($rows as $row) {
            $this->tracking->flash($this->tracking->cartChange($row, -(int) $row['qty']));
        }

        return back()->with('status', __('cart.flash.cleared'));
    }

    /**
     * Validate the submitted customisation options against the product's own
     * active options: every required option must be answered, and every
     * submitted value must belong to an active option/value of THIS product
     * (tampering / IDOR guard). Returns the clean option_id => value_id map.
     *
     * @param  array<int|string,mixed>  $submitted  options[option_id] = value_id
     * @return array<int,int>
     */
    private function validatedOptions(Product $product, array $submitted): array
    {
        $clean = [];

        foreach ($submitted as $optionId => $valueId) {
            if (! is_numeric($optionId) || ! is_numeric($valueId)) {
                continue;
            }
            $clean[(int) $optionId] = (int) $valueId;
        }

        $resolved = [];

        foreach ($product->options as $option) {
            $valueId = $clean[$option->id] ?? null;

            $value = $valueId
                ? $option->values->first(fn ($v) => (int) $v->id === $valueId && $v->is_active)
                : null;

            if (! $value) {
                if ($option->is_required) {
                    throw ValidationException::withMessages([
                        'options.'.$option->id => __('storefront.product.option_required', ['option' => $option->label]),
                    ]);
                }

                continue;
            }

            $resolved[$option->id] = $value->id;
        }

        return $resolved;
    }

    /**
     * Gift add-ons for the modal, already resolved for the view layer.
     *
     * @return array<int,array<string,mixed>>
     */
    private function suggestions(Product $product): array
    {
        $inCart = array_column($this->cart->rows(), 'product_id');
        $locale = app()->getLocale();

        return $this->catalog->giftSuggestions($product, 8)
            ->reject(function (Product $item) use ($inCart) {
                return in_array($item->id, $inCart, true);
            })
            ->take(6)
            ->map(function (Product $item) use ($locale) {
                return [
                    'id'         => $item->id,
                    'name'       => (string) $item->name,
                    'image'      => $item->primaryImageUrl(),
                    'price'      => $item->priceLabel(),
                    'url'        => route('product.show', [$locale, $item->slug]),
                    'has_variants' => (bool) $item->has_variants,
                    // A product with a required customisation option can't be
                    // added straight from the modal — send the shopper to the PDP.
                    'needs_options' => (bool) $item->has_variants || (int) ($item->required_options_count ?? 0) > 0,
                ];
            })
            ->values()
            ->all();
    }
}
