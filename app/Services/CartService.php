<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use App\Support\Formatting\Money;
use Illuminate\Support\Arr;

/**
 * Session-backed shopping cart. Works for guests and authenticated users alike;
 * on login the guest cart can later be merged into a persistent cart (Sprint 2+).
 * Rows store a snapshot (bilingual name, price, image) so the cart is resilient
 * to catalogue or locale changes after an item is added.
 */
class CartService
{
    private const SESSION_KEY = 'cart';

    /** @return array<string,array<string,mixed>> */
    public function rows(): array
    {
        return session(self::SESSION_KEY, []);
    }

    /**
     * @param array<int,int> $options map of option_id => value_id, as chosen
     *                                 on the product page. Each value's
     *                                 price_delta is folded into the line's
     *                                 unit price and a bilingual snapshot of
     *                                 the choice is stored on the row.
     *
     * @return string the cart row id, so callers can address the line afterwards
     */
    public function add(int $productId, ?int $variantId = null, int $qty = 1, array $options = []): string
    {
        $qty = max(1, $qty);

        $product = Product::active()->with(['variants', 'options.values'])->findOrFail($productId);

        $variant = null;
        if ($variantId) {
            $variant = $product->variants->firstWhere('id', $variantId);
        }

        $optionSnapshot = $this->resolveOptions($product, $options);
        $optionsDelta = array_sum(array_column($optionSnapshot, 'price_delta'));

        $baseUnitPrice = $variant ? (float) $variant->price : (float) $product->base_price;
        $unitPrice = $baseUnitPrice + $optionsDelta;
        $available = $variant ? (int) $variant->stock_quantity : (int) $product->stock_quantity;

        $rowId = $this->rowId($productId, $variantId, $optionSnapshot);
        $cart  = $this->rows();

        $currentQty = (int) Arr::get($cart, "$rowId.qty", 0);
        $newQty     = $currentQty + $qty;

        if ($available > 0) {
            $newQty = min($newQty, $available);
        }

        $cart[$rowId] = [
            'row_id'     => $rowId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'name'            => $product->getTranslations('name'),
            'variant'         => $variant ? $variant->getTranslations('name') : null,
            'options'         => $optionSnapshot ?: null,
            'slug'            => $product->slug,
            'image'           => $product->primaryImageUrl(),
            // base_unit_price = price before option add-ons; unit_price folds
            // them in. Both stored so the cart/order can itemise the add-on
            // separately without recomputing.
            'base_unit_price' => $baseUnitPrice,
            'unit_price'      => $unitPrice,
            'qty'             => $newQty,
        ];

        $this->persist($cart);

        return $rowId;
    }

    /**
     * Resolve the submitted option/value id pairs against the product's own
     * active options, ignoring anything that doesn't belong to it (the
     * server-side integrity check lives in CartController; this is defensive).
     * Returns a stable, sort_order-ordered bilingual snapshot.
     *
     * @param array<int,int> $options
     * @return array<int,array<string,mixed>>
     */
    private function resolveOptions(Product $product, array $options): array
    {
        if (empty($options)) {
            return [];
        }

        $snapshot = [];

        foreach ($product->options as $option) {
            $valueId = $options[$option->id] ?? null;
            if (! $valueId) {
                continue;
            }

            $value = $option->values->first(function ($v) use ($valueId) {
                return (int) $v->id === (int) $valueId && $v->is_active;
            });
            if (! $value) {
                continue;
            }

            $snapshot[] = [
                'option_id'   => $option->id,
                'value_id'    => $value->id,
                'key'         => $option->key,
                'label'       => $option->getTranslations('label'),
                'value_label' => $value->getTranslations('label'),
                'price_delta' => (float) $value->price_delta,
            ];
        }

        return $snapshot;
    }

    public function update(string $rowId, int $qty): void
    {
        $cart = $this->rows();

        if (! isset($cart[$rowId])) {
            return;
        }

        if ($qty <= 0) {
            unset($cart[$rowId]);
        } else {
            $cart[$rowId]['qty'] = $qty;
        }

        $this->persist($cart);
    }

    public function remove(string $rowId): void
    {
        $cart = $this->rows();
        unset($cart[$rowId]);
        $this->persist($cart);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return array_sum(array_column($this->rows(), 'qty'));
    }

    public function subtotal(): float
    {
        $total = 0.0;
        foreach ($this->rows() as $row) {
            $total += $row['unit_price'] * $row['qty'];
        }

        return $total;
    }

    public function subtotalLabel(): string
    {
        return Money::format($this->subtotal());
    }

    /**
     * @param array<int,array<string,mixed>> $options resolved option snapshot
     */
    private function rowId(int $productId, ?int $variantId, array $options = []): string
    {
        $valueIds = array_column($options, 'value_id');
        sort($valueIds);
        $optionKey = $valueIds ? ':'.implode('-', $valueIds) : '';

        return md5($productId.':'.($variantId ?: '0').$optionKey);
    }

    /** @param array<string,mixed> $cart */
    private function persist(array $cart): void
    {
        session([self::SESSION_KEY => $cart]);
    }
}
