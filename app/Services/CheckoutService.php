<?php

declare(strict_types=1);

namespace App\Services;

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
     * Create an order from cart and checkout data
     */
    public function createOrder(
        ?User $user,
        array $billingAddress,
        array $shippingAddress,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        ?string $customerNotes = null
    ): Order {
        return DB::transaction(function () use (
            $user,
            $billingAddress,
            $shippingAddress,
            $customerName,
            $customerEmail,
            $customerPhone,
            $customerNotes
        ) {
            // Use shipping as billing if not provided
            if (empty($shippingAddress)) {
                $shippingAddress = $billingAddress;
            }

            $totals = $this->calculateTotals();

            $order = Order::create([
                'user_id' => $user ? $user->id : null,
                'order_number' => $this->generateOrderNumber(),
                'status' => Order::STATUS_PENDING,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_cost' => $totals['shipping_cost'],
                'total_amount' => $totals['total_amount'],
                'customer_notes' => $customerNotes,
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
     * Mark order as paid
     */
    public function markOrderAsPaid(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment) {
            $order->update(['status' => Order::STATUS_PAID]);
            $payment->update(['status' => Payment::STATUS_CAPTURED]);

            // TODO: Trigger OrderPaid event (send confirmation email, etc)
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
