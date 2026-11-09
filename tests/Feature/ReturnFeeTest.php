<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnFeeTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrder(?string $reason = null, string $status = RefundRequest::STATUS_PENDING): Order
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'delivered_at' => now()->subDay()]);

        if ($reason) {
            RefundRequest::create(['order_id' => $order->id, 'user_id' => $user->id, 'reason' => $reason, 'status' => $status]);
        }

        return $order;
    }

    private function payment(Order $order, float $amount, float $refunded = 0.0): Payment
    {
        return Payment::create([
            'order_id' => $order->id, 'gateway' => 'moyasar', 'method' => 'mada',
            'status' => Payment::STATUS_CAPTURED, 'amount' => $amount, 'refunded_amount' => $refunded, 'currency' => 'SAR',
        ]);
    }

    public function test_returns_that_are_the_stores_fault_are_free(): void
    {
        foreach (RefundRequest::NO_FEE_REASONS as $reason) {
            $order = $this->deliveredOrder($reason);

            $this->assertEquals(200.0, $order->suggestedRefundAmount($this->payment($order, 200.0)), $reason);
        }
    }

    public function test_other_returns_pay_the_fifty_riyal_fee(): void
    {
        foreach (['no_longer_needed', 'other'] as $reason) {
            $order = $this->deliveredOrder($reason);

            $this->assertEquals(150.0, $order->suggestedRefundAmount($this->payment($order, 200.0)), $reason);
        }
    }

    public function test_a_delivered_order_refunded_without_a_request_pays_the_fee(): void
    {
        $order = $this->deliveredOrder();

        $this->assertEquals(150.0, $order->suggestedRefundAmount($this->payment($order, 200.0)));
    }

    public function test_a_rejected_request_does_not_waive_the_fee(): void
    {
        $order = $this->deliveredOrder('damaged', RefundRequest::STATUS_REJECTED);

        $this->assertEquals(150.0, $order->suggestedRefundAmount($this->payment($order, 200.0)));
    }

    public function test_the_suggested_amount_never_goes_negative(): void
    {
        $order = $this->deliveredOrder('other');

        $this->assertEquals(0.0, $order->suggestedRefundAmount($this->payment($order, 30.0)));
    }

    public function test_the_fee_is_recorded_when_it_was_actually_kept(): void
    {
        $order = $this->deliveredOrder('other');

        $this->assertTrue($order->recordReturnFee($this->payment($order, 200.0, 150.0)));
        $this->assertNotNull($order->fresh()->shipping_fee_deducted_at);
        $this->assertEquals(0.0, $order->fresh()->returnFee());
    }

    public function test_nothing_is_recorded_when_the_admin_gave_the_whole_amount_back(): void
    {
        $order = $this->deliveredOrder('other');

        $this->assertFalse($order->recordReturnFee($this->payment($order, 200.0, 200.0)));
        $this->assertNull($order->fresh()->shipping_fee_deducted_at);
    }

    public function test_nothing_is_recorded_when_the_return_was_free(): void
    {
        $order = $this->deliveredOrder('damaged');

        $this->assertFalse($order->recordReturnFee($this->payment($order, 200.0, 150.0)));
    }
}
