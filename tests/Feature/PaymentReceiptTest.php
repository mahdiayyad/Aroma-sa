<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Jobs\SendPaymentReceipt;
use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Receipts\PaymentReceiptService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function order(array $overrides = [], string $paymentStatus = Payment::STATUS_CAPTURED): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => Order::STATUS_PAID,
            'locale' => 'en',
            'subtotal' => 300,
            'total_amount' => 300,
        ], $overrides));

        $order->items()->create([
            'product_id' => Product::factory()->create()->id,
            'product_data' => ['name' => 'Midnight Abaya'],
            'unit_price' => 300,
            'quantity' => 1,
            'line_total' => 300,
        ]);

        $order->payment()->create([
            'gateway' => 'moyasar',
            'method' => 'visa',
            'status' => $paymentStatus,
            'transaction_id' => 'pay_'.$order->id,
            'amount' => 300,
            'gateway_response' => ['id' => 'pay_'.$order->id, 'source' => [
                'company' => 'visa', 'number' => '4111-11XX-XXXX-0123', 'authorization_code' => '623116',
            ]],
        ]);

        return $order;
    }

    private function runJob(Order $order, bool $resend = false): void
    {
        (new SendPaymentReceipt($order->id, $order->payment()->first()->id, $resend))
            ->handle(app(PaymentReceiptService::class));
    }

    /* Triggering --------------------------------------------------------- */

    public function test_a_confirmed_payment_queues_the_receipt_job(): void
    {
        Queue::fake();
        $order = $this->order(['status' => Order::STATUS_PENDING], Payment::STATUS_PENDING);
        $payment = $order->payment()->first();

        app(CheckoutService::class)->markOrderAsPaid($order, $payment);

        Queue::assertPushed(SendPaymentReceipt::class, function (SendPaymentReceipt $job) use ($order, $payment) {
            return $job->orderId === $order->id && $job->paymentId === $payment->id && $job->resend === false;
        });
    }

    public function test_a_webhook_and_the_customer_return_racing_fire_order_paid_once(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order(['status' => Order::STATUS_PENDING], Payment::STATUS_PENDING);
        $payment = $order->payment()->first();
        $staleCopy = Order::find($order->id); // loaded before the other path marks it paid

        app(CheckoutService::class)->markOrderAsPaid($order, $payment);
        app(CheckoutService::class)->markOrderAsPaid($staleCopy, $payment);

        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_a_late_payment_notification_never_moves_an_order_back_to_paid(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order(['status' => Order::STATUS_SHIPPED]);
        $payment = $order->payment()->first();
        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        app(CheckoutService::class)->markOrderAsPaid(Order::find($order->id), $payment);

        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        Event::assertNotDispatched(OrderPaid::class);
    }

    /* Issuing and delivery ------------------------------------------------ */

    public function test_the_job_issues_stores_and_emails_the_pdf_receipt(): void
    {
        Mail::fake();
        $order = $this->order();

        $this->runJob($order);

        $receipt = PaymentReceipt::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('RCPT-'.preg_replace('/^AR-/', '', $order->order_number), $receipt->receipt_number);
        $this->assertNotNull($receipt->emailed_at);
        $this->assertSame($order->customer_email, $receipt->emailed_to);
        $this->assertSame(1, $receipt->attempts);
        Storage::disk('local')->assertExists($receipt->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($receipt->pdf_path));

        Mail::assertSent(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) use ($order, $receipt) {
            $mail->build();
            $attachment = $mail->rawAttachments[0] ?? null;

            return $mail->hasTo($order->customer_email)
                && $attachment !== null
                && $attachment['name'] === 'receipt-'.$receipt->receipt_number.'.pdf'
                && strncmp($attachment['data'], '%PDF', 4) === 0;
        });
    }

    public function test_running_the_job_again_never_sends_a_second_receipt(): void
    {
        Mail::fake();
        $order = $this->order();

        $this->runJob($order);
        $this->runJob($order);

        Mail::assertSent(PaymentReceiptMail::class, 1);
        $this->assertSame(1, PaymentReceipt::count());
    }

    public function test_no_receipt_is_issued_for_an_unconfirmed_payment(): void
    {
        Mail::fake();
        $order = $this->order(['status' => Order::STATUS_PENDING], Payment::STATUS_PENDING);

        $this->runJob($order);

        $this->assertSame(0, PaymentReceipt::count());
        Mail::assertNothingSent();
    }

    public function test_a_mail_failure_is_recorded_and_the_retry_succeeds(): void
    {
        $order = $this->order();
        Mail::shouldReceive('to->send')->once()->andThrow(new RuntimeException('Mailgun unavailable'));

        try {
            $this->runJob($order);
            $this->fail('A mail failure must be rethrown so the queue retries the job.');
        } catch (RuntimeException $e) {
            $this->assertSame('Mailgun unavailable', $e->getMessage());
        }

        $receipt = PaymentReceipt::where('order_id', $order->id)->firstOrFail();
        $this->assertNull($receipt->emailed_at);
        $this->assertNull($receipt->sending_at, 'the claim is released so the retry can send');
        $this->assertSame('Mailgun unavailable', $receipt->last_error);

        Mail::swap(new \Illuminate\Support\Testing\Fakes\MailFake());
        $this->runJob($order);

        Mail::assertSent(PaymentReceiptMail::class, 1);
        $receipt->refresh();
        $this->assertNotNull($receipt->emailed_at);
        $this->assertNull($receipt->last_error);
        $this->assertSame(2, $receipt->attempts);
    }

    public function test_the_job_retries_on_its_own_terms(): void
    {
        $job = new SendPaymentReceipt(1, 1);

        $this->assertSame(5, $job->tries, 'the production worker runs with --tries=1, so the job sets its own');
        $this->assertSame([60, 300, 900, 3600], $job->backoff);
    }

    public function test_exhausted_retries_are_recorded_for_the_admin(): void
    {
        Mail::fake();
        $order = $this->order();
        app(PaymentReceiptService::class)->issue($order, $order->payment()->first());

        (new SendPaymentReceipt($order->id, $order->payment()->first()->id))->failed(new Exception('Mailgun rejected the message'));

        $receipt = PaymentReceipt::where('order_id', $order->id)->firstOrFail();
        $this->assertNotNull($receipt->failed_at);
        $this->assertSame('Mailgun rejected the message', $receipt->last_error);
    }

    public function test_an_invalid_customer_email_is_recorded_without_retrying(): void
    {
        Mail::fake();
        $order = $this->order(['customer_email' => 'not-an-email']);

        $this->runJob($order); // no exception: retrying can't fix a bad address

        Mail::assertNothingSent();
        $receipt = PaymentReceipt::where('order_id', $order->id)->firstOrFail();
        $this->assertNotNull($receipt->failed_at);
        $this->assertNull($receipt->emailed_at);
    }

    public function test_a_receipt_another_worker_is_sending_is_left_alone(): void
    {
        Mail::fake();
        $order = $this->order();
        $receipt = app(PaymentReceiptService::class)->issue($order, $order->payment()->first());
        $receipt->update(['sending_at' => now()]);

        $this->runJob($order);

        Mail::assertNothingSent();
    }

    public function test_an_abandoned_claim_is_taken_over(): void
    {
        Mail::fake();
        $order = $this->order();
        $receipt = app(PaymentReceiptService::class)->issue($order, $order->payment()->first());
        $receipt->update(['sending_at' => now()->subMinutes(30)]);

        $this->runJob($order);

        Mail::assertSent(PaymentReceiptMail::class, 1);
    }

    public function test_the_receipt_is_in_the_language_the_customer_shopped_in(): void
    {
        Mail::fake();
        $order = $this->order(['locale' => 'ar']);

        $this->runJob($order);

        $this->assertSame('ar', PaymentReceipt::where('order_id', $order->id)->value('locale'));
        Mail::assertSent(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) {
            return $mail->locale === 'ar';
        });
    }

    public function test_an_order_without_a_recorded_language_falls_back_to_the_store_default(): void
    {
        Mail::fake();
        $order = $this->order(['locale' => null]);

        $this->runJob($order);

        $this->assertSame(config('aroma.default_locale'), PaymentReceipt::where('order_id', $order->id)->value('locale'));
    }

    /* Card details on the customer-return path ---------------------------- */

    public function test_the_return_path_keeps_the_verified_card_details(): void
    {
        Queue::fake();
        config(['services.moyasar.secret_key' => 'sk_test']);
        $order = $this->order(['status' => Order::STATUS_PENDING], Payment::STATUS_PENDING);
        $order->payment()->update(['gateway_response' => null]);

        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'inv_9', 'status' => 'paid', 'payments' => [
            ['id' => 'pay_9', 'status' => 'paid', 'source' => ['company' => 'mada', 'number' => '4464-04XX-XXXX-4242', 'authorization_code' => 'AB12CD']],
        ]])]);

        $this->withSession(['checkout.gateway' => 'moyasar', 'checkout.reference' => 'inv_9', 'checkout.order_id' => $order->id])
            ->get(route('payment.callback'))
            ->assertRedirect();

        $payment = $order->payment()->first();
        $this->assertSame('inv_9', $payment->gateway_response['verification']['id']);
        $this->assertSame('4242', \App\Services\Receipts\ReceiptPaymentDetails::fromPayment($payment)->cardLast4);
        Queue::assertPushed(SendPaymentReceipt::class);
    }

    /* Access ----------------------------------------------------------------- */

    public function test_a_customer_downloads_their_own_receipt_only(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $order = $this->order(['user_id' => $owner->id]);
        $this->runJob($order);

        $response = $this->actingAs($owner)->get(route('order.receipt', $order));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs($owner)->get(route('order.show', $order))->assertSee(route('order.receipt', $order));
        $this->actingAs(User::factory()->create())->get(route('order.receipt', $order))->assertForbidden();
    }

    public function test_guests_cannot_download_receipts(): void
    {
        $order = $this->order(['user_id' => User::factory()->create()->id]);

        $this->get(route('order.receipt', $order))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_download_and_resend_the_receipt(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->order();

        // Downloading issues the receipt if the job hasn't run yet.
        $this->actingAs($admin)->get(route('admin.orders.receipt', $order))->assertOk();
        $this->assertSame(1, PaymentReceipt::count());

        $this->actingAs($admin)->post(route('admin.orders.receipt.resend', $order))
            ->assertSessionHas('status');
        Queue::assertPushed(SendPaymentReceipt::class, function (SendPaymentReceipt $job) use ($order) {
            return $job->orderId === $order->id && $job->resend === true;
        });

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(__('admin.receipts.panel_title'));
    }

    public function test_an_admin_resend_sends_again_even_after_delivery(): void
    {
        Mail::fake();
        $order = $this->order();

        $this->runJob($order);
        $this->runJob($order, true);

        Mail::assertSent(PaymentReceiptMail::class, 2);
    }

    public function test_an_unpaid_order_has_no_receipt_to_download_or_resend(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->order(['status' => Order::STATUS_PENDING], Payment::STATUS_PENDING);

        $this->actingAs($admin)->get(route('admin.orders.receipt', $order))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.orders.receipt.resend', $order))->assertSessionHas('error');

        Queue::assertNothingPushed();
        $this->assertSame(0, PaymentReceipt::count());
    }
}
