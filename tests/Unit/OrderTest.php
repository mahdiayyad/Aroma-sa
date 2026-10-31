<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Payment;
use Tests\TestCase;

/**
 * State-machine helpers on the Order model. All are pure functions of the
 * `status` attribute, so no database is required.
 */
class OrderTest extends TestCase
{
    private function orderWithStatus(string $status): Order
    {
        $order = new Order();
        $order->status = $status;

        return $order;
    }

    /* isPaid --------------------------------------------------------------- */

    public function test_paid_processing_shipped_and_delivered_all_count_as_paid(): void
    {
        foreach ([Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED] as $status) {
            $this->assertTrue($this->orderWithStatus($status)->isPaid(), "{$status} should be paid");
        }
    }

    public function test_pending_and_cancelled_are_not_paid(): void
    {
        $this->assertFalse($this->orderWithStatus(Order::STATUS_PENDING)->isPaid());
        $this->assertFalse($this->orderWithStatus(Order::STATUS_CANCELLED)->isPaid());
    }

    /* isPending ------------------------------------------------------------ */

    public function test_is_pending_reflects_only_the_pending_status(): void
    {
        $this->assertTrue($this->orderWithStatus(Order::STATUS_PENDING)->isPending());
        $this->assertFalse($this->orderWithStatus(Order::STATUS_PAID)->isPending());
    }

    /* isCancellable -------------------------------------------------------- */

    public function test_only_pending_and_paid_orders_are_cancellable(): void
    {
        $this->assertTrue($this->orderWithStatus(Order::STATUS_PENDING)->isCancellable());
        $this->assertTrue($this->orderWithStatus(Order::STATUS_PAID)->isCancellable());

        $this->assertFalse($this->orderWithStatus(Order::STATUS_SHIPPED)->isCancellable());
        $this->assertFalse($this->orderWithStatus(Order::STATUS_DELIVERED)->isCancellable());
        $this->assertFalse($this->orderWithStatus(Order::STATUS_CANCELLED)->isCancellable());
    }

    /* statusBadgeClass ----------------------------------------------------- */

    public function test_status_badge_class_maps_each_status_to_a_brand_tone(): void
    {
        $expected = [
            Order::STATUS_PAID       => 'aroma-badge-success',
            Order::STATUS_DELIVERED  => 'aroma-badge-success',
            Order::STATUS_PENDING    => 'aroma-badge-warning',
            Order::STATUS_PROCESSING => 'aroma-badge-info',
            Order::STATUS_SHIPPED    => 'aroma-badge-info',
            Order::STATUS_CANCELLED  => 'aroma-badge-danger',
        ];

        foreach ($expected as $status => $class) {
            $this->assertSame($class, $this->orderWithStatus($status)->statusBadgeClass());
        }
    }

    public function test_unknown_status_falls_back_to_neutral(): void
    {
        $this->assertSame('aroma-badge-neutral', $this->orderWithStatus('archived')->statusBadgeClass());
    }

    /* suggestedRefundAmount ------------------------------------------------- */

    private function payment(float $amount, float $refundedAmount = 0.0): Payment
    {
        $payment = new Payment();
        $payment->amount = $amount;
        $payment->refunded_amount = $refundedAmount;

        return $payment;
    }

    public function test_the_first_refund_deducts_shipping_from_the_remaining_amount(): void
    {
        $order = new Order();
        $order->shipping_cost = 25.0;
        $order->shipping_fee_deducted_at = null;

        $this->assertEquals(75.0, $order->suggestedRefundAmount($this->payment(100.0)));
    }

    public function test_a_second_refund_no_longer_deducts_shipping(): void
    {
        $order = new Order();
        $order->shipping_cost = 25.0;
        $order->shipping_fee_deducted_at = now();

        // 40 was already refunded on the first pass; the remaining 60
        // should NOT have shipping subtracted a second time.
        $this->assertEquals(60.0, $order->suggestedRefundAmount($this->payment(100.0, 40.0)));
    }

    public function test_no_shipping_cost_means_nothing_is_deducted(): void
    {
        $order = new Order();
        $order->shipping_cost = 0.0;
        $order->shipping_fee_deducted_at = null;

        $this->assertEquals(100.0, $order->suggestedRefundAmount($this->payment(100.0)));
    }

    public function test_the_suggested_amount_never_goes_negative(): void
    {
        $order = new Order();
        $order->shipping_cost = 150.0;
        $order->shipping_fee_deducted_at = null;

        $this->assertEquals(0.0, $order->suggestedRefundAmount($this->payment(100.0)));
    }
}
