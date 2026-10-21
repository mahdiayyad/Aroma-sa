<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Events\OrderPaymentFailed;
use App\Jobs\SendPaymentReceipt;
use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Moyasar webhooks as Moyasar actually sends them: JSON with the endpoint's
 * Secret Token in `secret_token`, `type` = payment_*, `data` = the payment.
 * Moyasar's API is always faked — no real payments or network calls.
 */
class MoyasarWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.moyasar.secret_key' => 'sk_test_key',
            'services.moyasar.webhook_secret' => self::SECRET,
        ]);
        Storage::fake('local');
    }

    private function order(float $total = 300.00): Order
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING,
            'subtotal' => $total,
            'total_amount' => $total,
        ]);
        $order->items()->create([
            'product_id' => Product::factory()->create()->id,
            'product_data' => ['name' => 'Midnight Abaya'],
            'unit_price' => $total,
            'quantity' => 1,
            'line_total' => $total,
        ]);
        $order->payment()->create([
            'gateway' => 'moyasar',
            'method' => 'mada',
            'status' => Payment::STATUS_PENDING,
            'reference_number' => 'inv_'.$order->id, // the invoice id saved when checkout started
            'amount' => $total,
            'currency' => 'SAR',
        ]);

        return $order;
    }

    /** What Moyasar's API returns for the payment. */
    private function moyasarPayment(Order $order, array $overrides = []): array
    {
        return array_merge([
            'id' => 'pay_'.$order->id,
            'status' => 'paid',
            'amount' => (int) round((float) $order->total_amount * 100),
            'currency' => 'SAR',
            'invoice_id' => 'inv_'.$order->id,
            'refunded' => 0,
            'source' => ['type' => 'creditcard', 'company' => 'mada', 'number' => '4464-04XX-XXXX-4242', 'authorization_code' => 'AB12CD', 'message' => 'APPROVED'],
        ], $overrides);
    }

    private function fakeMoyasar(array $payment, int $status = 200): void
    {
        Http::fake(['api.moyasar.com/v1/payments/*' => Http::response($payment, $status)]);
    }

    private function deliver(string $type, string $paymentId, ?string $token = self::SECRET, array $data = [])
    {
        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => $type,
            'created_at' => '2026-10-21T10:00:00Z',
            'account_name' => 'Aroma',
            'live' => false,
            'data' => array_merge(['id' => $paymentId], $data),
        ];
        if ($token !== null) {
            $payload['secret_token'] = $token;
        }

        return $this->postJson(route('payment.webhook'), $payload);
    }

    /* Authentication ----------------------------------------------------- */

    public function test_it_reaches_the_controller_without_a_csrf_token(): void
    {
        // A 419 here means real deliveries would never be processed.
        $this->deliver('payment_paid', 'pay_x', null)->assertStatus(401);
    }

    public function test_a_wrong_secret_token_is_rejected_before_anything_happens(): void
    {
        Http::fake();
        $order = $this->order();

        $this->deliver('payment_paid', 'pay_'.$order->id, 'not-the-secret')->assertStatus(401);

        Http::assertNothingSent();
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_an_unconfigured_secret_fails_closed(): void
    {
        config(['services.moyasar.webhook_secret' => '']);
        Http::fake();

        $this->deliver('payment_paid', 'pay_1', '')->assertStatus(401);
        $this->deliver('payment_paid', 'pay_1', 'anything')->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_the_secret_token_is_never_logged(): void
    {
        Log::spy();

        $this->deliver('payment_paid', 'pay_1', 'a-guessed-token')->assertStatus(401);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            return strpos(json_encode($context), 'a-guessed-token') === false;
        });
    }

    /* Successful payments -------------------------------------------------- */

    public function test_a_verified_paid_payment_marks_the_order_paid_and_queues_the_receipt(): void
    {
        Queue::fake();
        $order = $this->order();
        $this->fakeMoyasar($this->moyasarPayment($order));

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();

        $payment = $order->payment()->first();
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertSame('pay_'.$order->id, $payment->transaction_id);
        $this->assertSame('4464-04XX-XXXX-4242', $payment->gateway_response['source']['number']);

        // Verified with Moyasar using the secret key, not taken from the payload.
        Http::assertSent(function (Request $request) use ($order) {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.moyasar.com/v1/payments/pay_'.$order->id
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_key:'));
        });
        Queue::assertPushed(SendPaymentReceipt::class, 1);
    }

    public function test_the_payload_is_never_trusted_over_moyasars_api(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        // The webhook claims "paid", Moyasar says the payment failed.
        $this->fakeMoyasar($this->moyasarPayment($order, ['status' => 'failed']));

        $this->deliver('payment_paid', 'pay_'.$order->id, self::SECRET, ['status' => 'paid', 'amount' => 30000])->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        Event::assertNotDispatched(OrderPaid::class);
    }

    public function test_an_amount_mismatch_never_marks_the_order_paid(): void
    {
        Event::fake([OrderPaid::class]);
        Log::spy();
        $order = $this->order(300.00);
        $this->fakeMoyasar($this->moyasarPayment($order, ['amount' => 100])); // 1.00 SAR paid for a 300 SAR order

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        Event::assertNotDispatched(OrderPaid::class);
        Log::shouldHaveReceived('critical')->withArgs(function (string $message, array $context) use ($order) {
            return $context['order_id'] === $order->id && $context['paid_amount'] === 100 && $context['expected_amount'] === 30000;
        });
    }

    public function test_a_currency_mismatch_never_marks_the_order_paid(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        $this->fakeMoyasar($this->moyasarPayment($order, ['currency' => 'USD']));

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        Event::assertNotDispatched(OrderPaid::class);
    }

    public function test_a_payment_for_another_invoice_changes_nothing(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        $this->fakeMoyasar($this->moyasarPayment($order, ['id' => 'pay_other', 'invoice_id' => 'inv_unknown']));

        $this->deliver('payment_paid', 'pay_other')->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(1, Payment::count(), 'a webhook never creates payment records');
        Event::assertNotDispatched(OrderPaid::class);
    }

    public function test_a_captured_payment_also_completes_the_order(): void
    {
        Queue::fake();
        $order = $this->order();
        $this->fakeMoyasar($this->moyasarPayment($order, ['status' => 'captured']));

        $this->deliver('payment_captured', 'pay_'.$order->id)->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    /* Idempotency ------------------------------------------------------------ */

    public function test_redelivered_webhooks_send_one_receipt_and_one_confirmation(): void
    {
        config(['queue.default' => 'sync']);
        Mail::fake();
        $order = $this->order();
        $this->fakeMoyasar($this->moyasarPayment($order));

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();
        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();
        $this->deliver('payment_captured', 'pay_'.$order->id)->assertOk();

        Mail::assertSent(PaymentReceiptMail::class, 1);
        Mail::assertQueued(\App\Mail\OrderConfirmationMail::class, 1);
        $this->assertSame(1, PaymentReceipt::count());
        $this->assertNotNull(PaymentReceipt::first()->emailed_at);
    }

    public function test_a_webhook_after_the_customer_return_already_finalised_the_order_is_a_no_op(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        $order->update(['status' => Order::STATUS_PAID]);
        $order->payment()->update(['status' => Payment::STATUS_CAPTURED]);
        $this->fakeMoyasar($this->moyasarPayment($order));

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();

        Event::assertNotDispatched(OrderPaid::class);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_a_late_paid_webhook_never_moves_a_shipped_order_back(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        $order->update(['status' => Order::STATUS_SHIPPED]);
        $order->payment()->update(['status' => Payment::STATUS_CAPTURED]);
        $this->fakeMoyasar($this->moyasarPayment($order));

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();

        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
    }

    /* Transient failures and retries ------------------------------------- */

    public function test_moyasar_being_down_returns_503_so_the_webhook_is_retried(): void
    {
        Queue::fake();
        $order = $this->order();
        Http::fake(['api.moyasar.com/v1/payments/*' => Http::sequence()
            ->push(['message' => 'Service unavailable'], 503)
            ->push($this->moyasarPayment($order), 200)]);

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertStatus(503);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);

        // Moyasar's redelivery a minute later succeeds.
        $this->deliver('payment_paid', 'pay_'.$order->id)->assertOk();
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        Queue::assertPushed(SendPaymentReceipt::class, 1);
    }

    public function test_a_network_failure_returns_503(): void
    {
        $order = $this->order();
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: timed out');
        });

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertStatus(503);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_payment_moyasar_does_not_know_is_acknowledged(): void
    {
        $order = $this->order();
        $this->fakeMoyasar(['type' => 'invalid_request_error', 'message' => 'Object not found'], 404);

        $this->deliver('payment_paid', 'pay_unknown')->assertOk();
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_an_unexpected_error_returns_500_so_moyasar_retries(): void
    {
        $order = $this->order();
        config(['services.moyasar.secret_key' => '']); // a broken deployment: can't verify anything

        $this->deliver('payment_paid', 'pay_'.$order->id)->assertStatus(500);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    /* Failed, refunded and other events -------------------------------------- */

    public function test_a_failed_payment_is_recorded_and_the_customer_told_once(): void
    {
        Event::fake([OrderPaymentFailed::class, OrderPaid::class]);
        $order = $this->order();
        $this->fakeMoyasar($this->moyasarPayment($order, ['status' => 'failed', 'source' => ['message' => 'INSUFFICIENT FUNDS']]));

        $this->deliver('payment_failed', 'pay_'.$order->id)->assertOk();
        $this->deliver('payment_failed', 'pay_'.$order->id)->assertOk(); // redelivery

        $this->assertSame(Payment::STATUS_FAILED, $order->payment()->first()->status);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        Event::assertDispatchedTimes(OrderPaymentFailed::class, 1);
        Event::assertNotDispatched(OrderPaid::class);
    }

    public function test_a_failed_attempt_after_the_order_was_paid_is_ignored(): void
    {
        Event::fake([OrderPaymentFailed::class]);
        $order = $this->order();
        $order->update(['status' => Order::STATUS_PAID]);
        $order->payment()->update(['status' => Payment::STATUS_CAPTURED]);
        $this->fakeMoyasar($this->moyasarPayment($order, ['id' => 'pay_earlier', 'status' => 'failed']));

        $this->deliver('payment_failed', 'pay_earlier')->assertOk();

        $this->assertSame(Payment::STATUS_CAPTURED, $order->payment()->first()->status);
        Event::assertNotDispatched(OrderPaymentFailed::class);
    }

    public function test_a_refund_made_on_moyasar_is_recorded(): void
    {
        $order = $this->order(300.00);
        $order->update(['status' => Order::STATUS_PAID]);
        $order->payment()->update(['status' => Payment::STATUS_CAPTURED, 'transaction_id' => 'pay_'.$order->id]);
        $this->fakeMoyasar($this->moyasarPayment($order, ['status' => 'refunded', 'refunded' => 30000]));

        $this->deliver('payment_refunded', 'pay_'.$order->id)->assertOk();

        $payment = $order->payment()->first();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertEquals(300.00, (float) $payment->refunded_amount);
        $this->assertNotNull($payment->refunded_at);
    }

    public function test_non_payment_events_and_malformed_ids_are_acknowledged_without_api_calls(): void
    {
        Http::fake();

        $this->deliver('card_auth_authenticated', 'ca_123')->assertOk();
        $this->deliver('payment_paid', '../../invoices')->assertOk();
        $this->deliver('payment_paid', '')->assertOk();

        Http::assertNothingSent();
    }
}
