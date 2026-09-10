<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\Services\BaseService;
use Illuminate\Support\Facades\DB;

class CheckoutService extends BaseService
{
    private CartService $cart;

    public function __construct(CartService $cart)
    {
        $this->cart = $cart;
    }

    /**
     * Validate cart has items and sufficient stock
     */
    public function validateCart(): array
    {
        $rows = $this->cart->rows();

        if (empty($rows)) {
            return ['valid' => false, 'error' => __('checkout.errors.cart_empty')];
        }

        foreach ($rows as $row) {
            $product = Product::active()->findOrFail($row['product_id']);

            if (!$product->inStock()) {
                return ['valid' => false, 'error' => __('checkout.errors.product_out_of_stock', ['name' => $product->name])];
            }

            if ($row['variant_id'] ?? null) {
                $variant = $product->variants()->findOrFail($row['variant_id']);
                if ($variant->stock_quantity < $row['qty']) {
                    return ['valid' => false, 'error' => __('checkout.errors.insufficient_stock')];
                }
            } else {
                if ($product->stock_quantity < $row['qty']) {
                    return ['valid' => false, 'error' => __('checkout.errors.insufficient_stock')];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Calculate order totals from cart
     */
    public function calculateTotals(): array
    {
        $rows = $this->cart->rows();
        $subtotal = 0;

        foreach ($rows as $row) {
            $subtotal += $row['unit_price'] * $row['qty'];
        }

        // TODO: Add discount/coupon logic, tax calculation, shipping costs
        $discount = 0;
        $tax = 0;
        $shipping = 0;

        $total = $subtotal - $discount + $tax + $shipping;

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'shipping_cost' => $shipping,
            'total_amount' => $total,
        ];
    }

    /**
     * Create an order from the cart plus the checkout details gathered across
     * the address / gift-options / delivery steps. One structured array
     * rather than a growing positional-parameter list — see ai-docs checkout
     * refactor analysis, "createOrder() signature growth".
     *
     * @param array{
     *     billing_address: array<string,mixed>,
     *     shipping_address?: array<string,mixed>,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone: string,
     *     customer_notes?: ?string,
     *     is_gift?: bool,
     *     gift_message?: ?string,
     *     is_anonymous?: bool,
     *     gift_wrap_fee?: float|string,
     *     greeting_card_id?: ?int,
     *     greeting_card_fee?: float|string,
     *     gift_card_to?: ?string,
     *     gift_card_from?: ?string,
     *     gift_signature?: ?string,
     *     gift_media_url?: ?string,
     *     delivery_date?: ?string,
     *     delivery_time_slot?: ?string,
     *     delivery_instructions?: ?string,
     * } $details
     */
    public function createOrder(?User $user, array $details): Order
    {
        return DB::transaction(function () use ($user, $details) {
            $billingAddress = $details['billing_address'] ?? [];
            $shippingAddress = $details['shipping_address'] ?? [];

            // Use billing as shipping if a distinct one wasn't provided.
            if (empty($shippingAddress)) {
                $shippingAddress = $billingAddress;
            }

            $giftWrapFee = (float) ($details['gift_wrap_fee'] ?? 0);
            $greetingCardFee = (float) ($details['greeting_card_fee'] ?? 0);
            $totals = $this->calculateTotals();
            $totals['total_amount'] += $giftWrapFee + $greetingCardFee;

            $order = Order::create([
                'user_id' => $user ? $user->id : null,
                'order_number' => $this->generateOrderNumber(),
                'status' => Order::STATUS_PENDING,
                'customer_name' => $details['customer_name'],
                'customer_email' => $details['customer_email'],
                'customer_phone' => $details['customer_phone'],
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_cost' => $totals['shipping_cost'],
                'total_amount' => $totals['total_amount'],
                'customer_notes' => $details['customer_notes'] ?? null,

                // Gifting (see the "Recipient model" decision in the analysis:
                // shipping_address above already carries the recipient's name
                // + phone when is_gift is true — no separate columns for that).
                'is_gift' => (bool) ($details['is_gift'] ?? false),
                'gift_message' => $details['gift_message'] ?? null,
                'is_anonymous' => (bool) ($details['is_anonymous'] ?? false),
                'gift_wrap_fee' => $giftWrapFee,
                'greeting_card_id' => $details['greeting_card_id'] ?? null,
                'greeting_card_fee' => $greetingCardFee,
                'gift_card_to' => $details['gift_card_to'] ?? null,
                'gift_card_from' => $details['gift_card_from'] ?? null,
                'gift_signature' => $details['gift_signature'] ?? null,
                'gift_media_url' => $details['gift_media_url'] ?? null,

                // Delivery scheduling — data capture only, see config('aroma.delivery').
                'delivery_date' => $details['delivery_date'] ?? null,
                'delivery_time_slot' => $details['delivery_time_slot'] ?? null,
                'delivery_instructions' => $details['delivery_instructions'] ?? null,
            ]);

            // Create order items from cart
            $rows = $this->cart->rows();
            foreach ($rows as $row) {
                $product = Product::findOrFail($row['product_id']);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $row['variant_id'] ?? null,
                    'product_data' => [
                        'name' => $product->name,
                        'image' => $product->primaryImageUrl(),
                        'sku' => $product->sku,
                    ],
                    'variant_data' => $row['variant'] ?? null,
                    'options_snapshot' => $row['options'] ?? null,
                    'unit_price' => $row['unit_price'],
                    'quantity' => $row['qty'],
                    'line_total' => $row['unit_price'] * $row['qty'],
                ]);

                // TODO: Deduct inventory (add stock lock/reservation)
                // TODO: Trigger OrderCreated event
            }

            return $order;
        });
    }

    /**
     * Create payment record for order
     */
    public function createPayment(
        Order $order,
        string $gateway,
        string $method,
        ?array $gatewayResponse = null
    ): Payment {
        return Payment::create([
            'order_id' => $order->id,
            'gateway' => $gateway,
            'method' => $method,
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->total_amount,
            'currency' => 'SAR',
            'gateway_response' => $gatewayResponse,
        ]);
    }

    /**
     * Mark an order as paid. The single source of truth for the paid
     * transition — both the customer-return callback (CheckoutController)
     * and the Moyasar webhook (MoyasarPaymentService) route through here, so
     * OrderPaid fires exactly once no matter which one lands first.
     */
    public function markOrderAsPaid(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment) {
            $wasAlreadyPaid = $order->isPaid();

            $order->update(['status' => Order::STATUS_PAID]);
            $payment->update(['status' => Payment::STATUS_CAPTURED]);

            // Guard against a double-dispatch race between the webhook and the
            // callback (both can independently observe "gateway says paid").
            if (! $wasAlreadyPaid) {
                event(new OrderPaid($order, $payment));
            }

            // TODO: Deduct stock permanently
        });
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $year = now()->year;
        $sequence = Order::whereYear('created_at', $year)->count() + 1;

        return sprintf('AR-%d-%06d', $year, $sequence);
    }
}
