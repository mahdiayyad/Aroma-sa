<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\RefundProcessedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Api\Admin\OrderController::refund() — the only reachable entry point for a
 * Moyasar refund today (no Blade admin UI exists for it, unlike Tamara's).
 * Covers MoyasarPaymentService::refundPayment()'s own fixes: its default
 * amount used to ignore any prior refunded_amount, and shipping is now
 * subtracted (once per order) with a customer notification either way.
 */
class AdminOrderRefundTest extends TestCase
{
    use RefreshDatabase;

    private array $address = ['recipient_name' => 'Sara', 'phone' => '0500000000', 'street_address' => 'K', 'city' => 'Riyadh'];

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** @return array{0:Order,1:Payment} */
    private function moyasarOrder(float $shippingCost = 0.0, ?\Illuminate\Support\Carbon $shippingFeeDeductedAt = null): array
    {
        static $n = 0;
        $n++;

        $order = Order::create([
            'order_number' => 'AR-2026-0008'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'status' => Order::STATUS_PAID,
            'customer_name' => 'Sara', 'customer_email' => 's@e.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 100, 'shipping_cost' => $shippingCost, 'total_amount' => 100 + $shippingCost,
            'shipping_fee_deducted_at' => $shippingFeeDeductedAt,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => 'moyasar', 'method' => 'mada',
            'status' => Payment::STATUS_CAPTURED, 'amount' => 100 + $shippingCost, 'refunded_amount' => 0, 'currency' => 'SAR',
            'transaction_id' => 'pay_123',
        ]);

        return [$order, $payment];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.moyasar.secret_key' => 'sk_test']);
    }

    public function test_a_refund_with_no_amount_deducts_shipping_on_the_first_pass(): void
    {
        Mail::fake();
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'ref_1'], 200)]);
        // Payment amount is subtotal+shipping (100+25=125): the first refund
        // should hand back everything except the 25 SAR shipping fee.
        [$order, $payment] = $this->moyasarOrder(25.0);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/orders/'.$order->id.'/refund', [])->assertOk();

        $this->assertEquals(100.0, (float) $payment->fresh()->refunded_amount);
        $this->assertNotNull($order->fresh()->shipping_fee_deducted_at);
        Mail::assertSent(RefundProcessedMail::class, function ($mail) {
            return $mail->refundAmount === 100.0 && $mail->shippingDeducted === true;
        });

        Http::assertSent(function ($request) {
            return strpos($request->url(), '/payments/pay_123/refund') !== false
                && $request['amount'] === 10000; // halalas
        });
    }

    public function test_a_second_refund_on_the_same_order_no_longer_deducts_shipping(): void
    {
        Mail::fake();
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'ref_2'], 200)]);
        // Simulates right after the first refund above: 100 of 125 already
        // refunded, shipping already marked deducted — the remaining 25
        // should go out whole, with no further shipping deduction.
        [$order, $payment] = $this->moyasarOrder(25.0, now()->subHour());
        $payment->update(['refunded_amount' => 100.0]);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/orders/'.$order->id.'/refund', [])->assertOk();

        $this->assertEquals(125.0, (float) $payment->fresh()->refunded_amount);
        Mail::assertSent(RefundProcessedMail::class, function ($mail) {
            return $mail->refundAmount === 25.0 && $mail->shippingDeducted === false;
        });
    }

    public function test_a_partial_refund_leaves_the_payment_captured_so_a_second_refund_is_still_possible(): void
    {
        Mail::fake();
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'ref_4'], 200)]);
        [$order, $payment] = $this->moyasarOrder(0.0);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/orders/'.$order->id.'/refund', ['amount' => 40.0])->assertOk();

        $fresh = $payment->fresh();
        $this->assertSame(Payment::STATUS_CAPTURED, $fresh->status, 'a partial refund must not block further refunds');
        $this->assertEquals(40.0, (float) $fresh->refunded_amount);

        // A payment flipped to REFUNDED after a partial refund would no
        // longer match the admin refund endpoint's own query — confirms the
        // fix actually restores reachability, not just the status value.
        $this->postJson('/api/admin/orders/'.$order->id.'/refund', ['amount' => 60.0])->assertOk();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertEquals(100.0, (float) $payment->fresh()->refunded_amount);
    }

    public function test_an_explicit_amount_overrides_the_suggested_one(): void
    {
        Mail::fake();
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'ref_3'], 200)]);
        [$order, $payment] = $this->moyasarOrder(25.0);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/orders/'.$order->id.'/refund', ['amount' => 50.0])->assertOk();

        $this->assertEquals(50.0, (float) $payment->fresh()->refunded_amount);
    }
}
