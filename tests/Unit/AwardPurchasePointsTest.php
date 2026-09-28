<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardPurchasePointsTest extends TestCase
{
    use RefreshDatabase;

    private array $address = [
        'recipient_name' => 'Sara', 'phone' => '0500000000', 'street_address' => 'K',
        'city' => 'Riyadh', 'region' => 'Riyadh', 'postal_code' => '',
    ];

    private function payFor(?User $user): Order
    {
        $cart = new CartService();
        $checkout = app()->makeWith(CheckoutService::class, ['cart' => $cart]);
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 5]);
        $cart->add($product->id, null, 1);

        $order = $checkout->createOrder($user, [
            'billing_address' => $this->address, 'customer_name' => 'Sara',
            'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
        ]);
        $payment = $checkout->createPayment($order, 'moyasar', 'mada');

        $checkout->markOrderAsPaid($order, $payment);

        return $order->fresh();
    }

    public function test_a_paid_order_credits_the_buyer_purchase_points(): void
    {
        $user = User::factory()->create(['loyalty_points' => 0]);

        $order = $this->payFor($user);

        $expectedPoints = (int) round(((float) $order->total_amount) * config('aroma.rewards.points_per_sar', 10));
        $this->assertSame($expectedPoints, $user->fresh()->loyalty_points);

        $tx = PointTransaction::where('user_id', $user->id)
            ->where('type', PointTransaction::TYPE_PURCHASE_REWARD)
            ->first();

        $this->assertNotNull($tx);
        $this->assertSame($order->id, $tx->reference_id);
        $this->assertSame($expectedPoints, $tx->points);
        $this->assertSame($expectedPoints, $tx->balance_after);
    }

    public function test_a_guest_order_does_not_error_or_credit_anyone(): void
    {
        $order = $this->payFor(null);

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(0, PointTransaction::where('type', PointTransaction::TYPE_PURCHASE_REWARD)->count());
    }
}
