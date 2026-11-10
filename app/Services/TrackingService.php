<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\MarketingPixels;
use Illuminate\Support\Str;

/**
 * Builds the store's e-commerce tracking events and hands them to the browser,
 * where public/js/aroma-tracking.js sends each one to the Meta Pixel, the
 * TikTok Pixel and the GTM dataLayer.
 *
 * Every event uses one GA4-style shape so the three destinations can't drift:
 *   ['event' => 'add_to_cart', 'event_id' => '...', 'ecommerce' => [
 *       'currency' => 'SAR', 'value' => 450.0, 'items' => [[item_id, item_name, price, quantity, ...]]]]
 *
 * Delivery: push() for the page being rendered, flash() for the next page
 * after a redirect, and inline in JSON responses for AJAX actions.
 *
 * item_id is the product SKU (the same id the product pages publish in their
 * schema.org data), so a Meta/TikTok catalogue must use SKUs as its ids.
 */
class TrackingService
{
    private const SESSION_QUEUE = 'tracking.queue';
    private const SESSION_CHECKOUT = 'tracking.checkout_signature';

    /** @var array<int,array<string,mixed>> */
    private $events = [];

    /** @var array<string,array<string,mixed>> events the page fires itself (e.g. on form submit) */
    private $deferred = [];

    public function enabled(): bool
    {
        return MarketingPixels::enabled();
    }

    /* ------------------------------------------------------------------ */
    /* Delivery                                                            */
    /* ------------------------------------------------------------------ */

    /** @param array<string,mixed>|null $event */
    public function push(?array $event): void
    {
        if ($event !== null && $this->enabled()) {
            $this->events[] = $event;
        }
    }

    /** @param array<string,mixed>|null $event */
    public function flash(?array $event): void
    {
        if ($event !== null && $this->enabled()) {
            session()->push(self::SESSION_QUEUE, $event);
        }
    }

    /** @param array<string,mixed>|null $event */
    public function defer(string $name, ?array $event): void
    {
        if ($event !== null && $this->enabled()) {
            $this->deferred[$name] = $event;
        }
    }

    /**
     * Events for a JSON response, which aroma-http.js dispatches.
     *
     * @param array<int,array<string,mixed>|null> $events
     * @return array<int,array<string,mixed>>
     */
    public function forJson(array $events): array
    {
        return $this->enabled() ? array_values(array_filter($events)) : [];
    }

    /**
     * Everything the page should fire: this request's events plus any
     * queued by an earlier request. Queued events are consumed here, so a
     * reload never replays them.
     *
     * @return array{events:array<int,array<string,mixed>>,deferred:array<string,array<string,mixed>>}
     */
    public function drain(): array
    {
        $queued = (array) session()->pull(self::SESSION_QUEUE, []);
        $drained = [
            'events' => array_values(array_merge($queued, $this->events)),
            'deferred' => $this->deferred,
        ];

        // Handed out once, even if this instance outlives the request.
        $this->events = [];
        $this->deferred = [];

        return $drained;
    }

    /* ------------------------------------------------------------------ */
    /* Purchase                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Queue the Purchase event for a paid order, at most once per order ever:
     * the claim is a single conditional UPDATE, so a refreshed callback, a
     * second tab or a webhook race can't produce a second one.
     */
    public function queuePurchase(Order $order): void
    {
        if (! $this->enabled() || ! $order->isPaid()) {
            return;
        }

        $claimed = Order::query()
            ->whereKey($order->getKey())
            ->whereNull('purchase_tracked_at')
            ->update(['purchase_tracked_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        session()->forget(self::SESSION_CHECKOUT);
        $this->flash($this->purchase($order));
    }

    /* ------------------------------------------------------------------ */
    /* Event builders                                                      */
    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed> */
    public function viewItem(Product $product): array
    {
        $item = $this->productItem($product);

        return $this->event('view_item', (float) $item['price'], [$item]);
    }

    /** @return array<string,mixed> */
    public function addToWishlist(Product $product): array
    {
        $item = $this->productItem($product);

        return $this->event('add_to_wishlist', (float) $item['price'], [$item]);
    }

    /**
     * @param iterable<Product> $products the products shown on the page
     * @return array<string,mixed>|null
     */
    public function viewItemList(Category $category, iterable $products): ?array
    {
        $items = [];
        foreach ($products as $index => $product) {
            $items[] = $this->productItem($product) + ['index' => $index];
        }

        if ($items === []) {
            return null;
        }

        return $this->event('view_item_list', null, $items, [
            'item_list_id' => (string) $category->slug,
            'item_list_name' => $this->english($category->getTranslations('name')),
        ]);
    }

    /**
     * A cart line changed by $delta units: add_to_cart when it grew,
     * remove_from_cart when it shrank, nothing when it didn't move (e.g. the
     * stock cap swallowed the add).
     *
     * @param array<string,mixed> $row a CartService row (before or after the change)
     * @return array<string,mixed>|null
     */
    public function cartChange(array $row, int $delta): ?array
    {
        if ($delta === 0) {
            return null;
        }

        $item = $this->cartItems([array_merge($row, ['qty' => abs($delta)])])[0];

        return $this->event(
            $delta > 0 ? 'add_to_cart' : 'remove_from_cart',
            round($item['price'] * abs($delta), 2),
            [$item]
        );
    }

    /**
     * @param array<string,array<string,mixed>> $rows
     * @return array<string,mixed>|null
     */
    public function viewCart(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $items = $this->cartItems($rows);

        return $this->event('view_cart', $this->itemsValue($items), $items);
    }

    /**
     * InitiateCheckout once per cart: the first checkout page fires it, the
     * following steps, a reload or the back button don't. A changed cart counts
     * as a new checkout.
     *
     * @param array<string,array<string,mixed>> $rows
     */
    public function beginCheckout(array $rows, ?string $coupon = null): void
    {
        if ($rows === [] || ! $this->enabled()) {
            return;
        }

        $signature = md5((string) json_encode(array_map(function (array $row) {
            return (int) $row['qty'];
        }, $rows)));

        if (session(self::SESSION_CHECKOUT) === $signature) {
            return;
        }
        session([self::SESSION_CHECKOUT => $signature]);

        $items = $this->cartItems($rows);
        $this->push($this->event('begin_checkout', $this->itemsValue($items), $items, array_filter([
            'coupon' => $coupon,
        ])));
    }

    /**
     * The payment step's AddPaymentInfo, fired by the page when the payment
     * form is actually submitted (payment_type is filled in there).
     *
     * @param array<string,array<string,mixed>> $rows
     * @param array<string,mixed> $totals CheckoutService totals incl. gift extras
     * @return array<string,mixed>|null
     */
    public function addPaymentInfo(array $rows, array $totals, ?string $coupon = null): ?array
    {
        if ($rows === []) {
            return null;
        }

        return $this->event('add_payment_info', round((float) ($totals['total_amount'] ?? 0), 2), $this->cartItems($rows), array_filter([
            'coupon' => $coupon,
        ]));
    }

    /**
     * value is what the customer paid (order total: items + shipping + gift
     * extras − discount). The event id is derived from the order number, so it
     * stays stable for platform-side deduplication.
     *
     * @return array<string,mixed>
     */
    public function purchase(Order $order): array
    {
        $order->loadMissing('items');
        $products = $this->productsById($order->items->pluck('product_id')->all());

        $items = [];
        foreach ($order->items as $orderItem) {
            $data = (array) $orderItem->product_data;
            $product = $products[(int) $orderItem->product_id] ?? null;
            $name = $data['name'] ?? '';

            // The order line keeps the name in the shopping language; take the
            // product's English name like every other event, when it still exists.
            $items[] = array_filter([
                'item_id' => (string) (($data['sku'] ?? null) ?: ($product->sku ?? null) ?: $orderItem->product_id),
                'item_name' => $product
                    ? $this->english($product->getTranslations('name'))
                    : (is_array($name) ? $this->english($name) : (string) $name),
                'item_category' => $product ? $this->categoryName($product) : null,
                'item_variant' => $orderItem->variant_data ? $this->english((array) $orderItem->variant_data) : null,
                'price' => round((float) $orderItem->unit_price, 2),
                'quantity' => (int) $orderItem->quantity,
            ], function ($value) {
                return $value !== null && $value !== '';
            });
        }

        $event = $this->event('purchase', round((float) $order->total_amount, 2), $items, array_filter([
            'transaction_id' => (string) $order->order_number,
            'shipping' => round((float) $order->shipping_cost, 2),
            'tax' => round((float) $order->tax_amount, 2),
            'discount' => round((float) $order->discount_amount, 2),
            'coupon' => $order->promo_code ?: null,
        ], function ($value) {
            return $value !== null;
        }));
        $event['event_id'] = 'purchase-'.$order->order_number;
        $event['once'] = true;

        return $event;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $extra more `ecommerce` fields (transaction_id, coupon, …)
     * @return array<string,mixed>
     */
    private function event(string $name, ?float $value, array $items, array $extra = []): array
    {
        $ecommerce = ['currency' => $this->currency()];
        if ($value !== null) {
            $ecommerce['value'] = $value;
        }

        return [
            'event' => $name,
            'event_id' => $name.'-'.Str::uuid()->toString(),
            'ecommerce' => array_merge($ecommerce, $extra, ['items' => array_values($items)]),
        ];
    }

    /** @return array<string,mixed> */
    private function productItem(Product $product): array
    {
        return array_filter([
            'item_id' => $this->itemId($product->sku, $product->id),
            'item_name' => $this->english($product->getTranslations('name')),
            'item_category' => $this->categoryName($product),
            'price' => round((float) $product->base_price, 2),
            'quantity' => 1,
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Cart rows hold a bilingual snapshot but no SKU, so the products are
     * looked up once for the whole list.
     *
     * @param array<int|string,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function cartItems(array $rows): array
    {
        $products = $this->productsById(array_column($rows, 'product_id'));
        $items = [];

        foreach ($rows as $row) {
            $product = $products[(int) $row['product_id']] ?? null;
            $variant = array_filter(array_merge(
                [! empty($row['variant']) ? $this->english((array) $row['variant']) : null],
                array_map(function (array $option) {
                    return $this->english((array) ($option['value_label'] ?? []));
                }, (array) ($row['options'] ?? []))
            ));

            $items[] = array_filter([
                'item_id' => $this->itemId($product->sku ?? null, (int) $row['product_id']),
                'item_name' => $this->english((array) $row['name']),
                'item_category' => $product ? $this->categoryName($product) : null,
                'item_variant' => $variant ? implode(' / ', $variant) : null,
                'price' => round((float) $row['unit_price'], 2),
                'quantity' => (int) $row['qty'],
            ], function ($value) {
                return $value !== null && $value !== '';
            });
        }

        return $items;
    }

    /**
     * @param array<int,mixed> $ids
     * @return array<int,Product>
     */
    private function productsById(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        return Product::withTrashed()->with('category')->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /** @param array<int,array<string,mixed>> $items */
    private function itemsValue(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['price'] * (int) $item['quantity'];
        }

        return round($total, 2);
    }

    /** @param mixed $sku */
    private function itemId($sku, int $id): string
    {
        $sku = trim((string) $sku);

        return $sku !== '' ? $sku : (string) $id;
    }

    private function categoryName(Product $product): ?string
    {
        return $product->category ? $this->english($product->category->getTranslations('name')) : null;
    }

    /**
     * Event names stay in English whatever the shopping language, so reports
     * don't split one product across two names.
     *
     * @param array<string,mixed> $translations
     */
    private function english(array $translations): string
    {
        $value = $translations['en'] ?? null;
        if (! is_string($value) || trim($value) === '') {
            $value = reset($translations);
        }

        return is_string($value) ? trim($value) : '';
    }

    private function currency(): string
    {
        return (string) config('aroma.currency.code', 'SAR');
    }
}
