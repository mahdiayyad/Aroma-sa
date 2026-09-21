<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Payment\TabbyPaymentService;
use App\Services\Payment\TamaraPaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The BNPL gateways build their payloads from an in-memory order (no DB) and
 * talk to faked HTTP endpoints, so these run fast and cover the edge paths.
 */
class BnplGatewayTest extends TestCase
{
    private function order(): Order
    {
        $order = new Order();
        $order->forceFill([
            'order_number'     => 'AR-2026-000001',
            'total_amount'     => 300,
            'customer_name'    => 'Sara Al Qahtani',
            'customer_email'   => 'sara@example.com',
            'customer_phone'   => '0500000000',
            'shipping_address' => ['street_address' => 'King Fahd Rd', 'city' => 'Riyadh'],
        ]);

        $item = new OrderItem();
        $item->forceFill([
            'id' => 1, 'product_id' => 5, 'quantity' => 2,
            'unit_price' => 150, 'line_total' => 300,
            'product_data' => ['name' => 'Rose Oud', 'sku' => 'AR-ROSE'],
        ]);

        $order->setRelation('items', new Collection([$item]));

        return $order;
    }

    /* Tabby ---------------------------------------------------------------- */

    public function test_tabby_reports_failure_when_not_configured(): void
    {
        config(['services.tabby.secret_key' => '', 'services.tabby.merchant_code' => '']);

        $result = (new TabbyPaymentService())->createCheckout($this->order());

        $this->assertFalse($result['success']);
    }

    public function test_tabby_rejected_session_is_not_successful(): void
    {
        config(['services.tabby.secret_key' => 'sk', 'services.tabby.merchant_code' => 'm']);
        Http::fake(['api.tabby.ai/*' => Http::response(['status' => 'rejected'], 200)]);

        $result = (new TabbyPaymentService())->createCheckout($this->order());

        $this->assertFalse($result['success']);
    }

    public function test_tabby_returns_the_installments_web_url(): void
    {
        config(['services.tabby.secret_key' => 'sk', 'services.tabby.merchant_code' => 'm']);
        Http::fake(['api.tabby.ai/*' => Http::response([
            'status'  => 'created',
            'payment' => ['id' => 'pay_1'],
            'configuration' => ['available_products' => ['installments' => [
                ['web_url' => 'https://checkout.tabby.ai/pay_1'],
            ]]],
        ], 200)]);

        $result = (new TabbyPaymentService())->createCheckout($this->order());

        $this->assertTrue($result['success']);
        $this->assertSame('https://checkout.tabby.ai/pay_1', $result['redirect_url']);
        $this->assertSame('pay_1', $result['reference']);
    }

    public function test_tabby_status_maps_authorized_to_paid(): void
    {
        config(['services.tabby.secret_key' => 'sk']);
        Http::fake(['api.tabby.ai/*' => Http::response(['id' => 'p', 'status' => 'AUTHORIZED'], 200)]);

        $status = (new TabbyPaymentService())->fetchStatus('p');

        $this->assertTrue($status['paid']);
    }

    /* Tamara --------------------------------------------------------------- */

    public function test_tamara_returns_the_checkout_url(): void
    {
        config(['services.tamara.api_token' => 'tok']);
        Http::fake(['*tamara.co/*' => Http::response([
            'order_id' => 'o1', 'checkout_url' => 'https://checkout.tamara.co/c1',
        ], 200)]);

        $result = (new TamaraPaymentService())->createCheckout($this->order());

        $this->assertTrue($result['success']);
        $this->assertSame('https://checkout.tamara.co/c1', $result['redirect_url']);
        $this->assertSame('o1', $result['reference']);
    }

    public function test_tamara_status_maps_approved_to_paid(): void
    {
        config(['services.tamara.api_token' => 'tok']);
        Http::fake(['*tamara.co/*' => Http::response(['status' => 'approved'], 200)]);

        $status = (new TamaraPaymentService())->fetchStatus('o1');

        $this->assertTrue($status['paid']);
    }

    public function test_tamara_not_configured_returns_failure(): void
    {
        config(['services.tamara.api_token' => '']);

        $this->assertFalse((new TamaraPaymentService())->createCheckout($this->order())['success']);
    }

    /* Tamara: checkout payload ------------------------------------------- */

    private function orderWithExtras(array $overrides = []): Order
    {
        $order = $this->order();
        // 300 items + 25 shipping + 45 tax - 30 promo + 15 gift wrap + 10 card = 365
        $order->forceFill(array_merge([
            'shipping_cost'     => 25,
            'tax_amount'        => 45,
            'discount_amount'   => 30,
            'promo_code'        => 'SAVE10',
            'gift_wrap_fee'     => 15,
            'greeting_card_fee' => 10,
            'total_amount'      => 365,
        ], $overrides));

        return $order;
    }

    public function test_tamara_payload_carries_tax_shipping_discount_and_gift_fees(): void
    {
        config(['services.tamara.api_token' => 'tok']);
        Http::fake(['*tamara.co/*' => Http::response(['order_id' => 'o1', 'checkout_url' => 'https://checkout.tamara.co/c1'], 200)]);

        (new TamaraPaymentService())->createCheckout($this->orderWithExtras());

        Http::assertSent(function ($request) {
            $b = $request->data();

            $itemsTotal = array_sum(array_map(function ($i) { return (float) $i['total_amount']['amount']; }, $b['items']));
            $skus       = array_column($b['items'], 'sku');

            return $b['total_amount']['amount'] === '365.00'
                && $b['tax_amount']['amount'] === '45.00'
                && $b['shipping_amount']['amount'] === '25.00'
                && $b['discount']['name'] === 'SAVE10'
                && $b['discount']['amount']['amount'] === '30.00'
                && in_array('GIFT-WRAP', $skus, true)
                && in_array('GREETING-CARD', $skus, true)
                // items + shipping + tax - discount must equal the charged total
                && abs($itemsTotal + 25 + 45 - 30 - 365) < 0.01;
        });
    }

    public function test_tamara_payload_has_zero_tax_and_shipping_and_no_discount_when_none_apply(): void
    {
        config(['services.tamara.api_token' => 'tok']);
        Http::fake(['*tamara.co/*' => Http::response(['order_id' => 'o1', 'checkout_url' => 'https://checkout.tamara.co/c1'], 200)]);

        (new TamaraPaymentService())->createCheckout($this->order());

        Http::assertSent(function ($request) {
            $b = $request->data();

            return $b['tax_amount']['amount'] === '0.00'
                && $b['shipping_amount']['amount'] === '0.00'
                && ! array_key_exists('discount', $b)
                && $b['consumer']['phone_number'] === '+966500000000';
        });
    }

    /* Tamara: pre-checkout eligibility ----------------------------------- */

    private function eligibilitySetup(): void
    {
        Cache::flush();
        config(['services.tamara.api_token' => 'tok', 'services.tamara.eligibility_timeout_ms' => 800]);
    }

    public function test_tamara_eligibility_false_when_tamara_says_not_eligible(): void
    {
        $this->eligibilitySetup();
        Http::fake(['*tamara.co/pre-checkout/v1/eligibility' => Http::response(['is_eligible' => false], 200)]);

        $this->assertFalse((new TamaraPaymentService())->checkEligibility(300.0, '0500000000', 's@e.com'));

        Http::assertSent(function ($request) {
            $b = $request->data();

            return $b['order'] === ['amount' => 300.0, 'currency' => 'SAR']
                && $b['customer']['phone_number'] === '+966500000000'
                && $b['customer']['email'] === 's@e.com';
        });
    }

    public function test_tamara_eligibility_true_when_eligible(): void
    {
        $this->eligibilitySetup();
        Http::fake(['*tamara.co/pre-checkout/v1/eligibility' => Http::response(['is_eligible' => true], 200)]);

        $this->assertTrue((new TamaraPaymentService())->checkEligibility(300.0, '+966500000000'));
    }

    public function test_tamara_eligibility_fails_open_on_timeout_error_and_garbage(): void
    {
        $this->eligibilitySetup();
        $service = new TamaraPaymentService();

        Http::fake(['*tamara.co/*' => function () { throw new ConnectionException('timed out'); }]);
        $this->assertTrue($service->checkEligibility(300.0, '0500000000'), 'timeout');

        Cache::flush();
        Http::fake(['*tamara.co/*' => Http::response(['message' => 'boom'], 500)]);
        $this->assertTrue($service->checkEligibility(301.0, '0500000000'), '5xx');

        Cache::flush();
        Http::fake(['*tamara.co/*' => Http::response(['is_eligible' => 'maybe'], 200)]);
        $this->assertTrue($service->checkEligibility(302.0, '0500000000'), 'non-boolean answer');
    }

    public function test_tamara_eligibility_skips_the_call_without_a_usable_phone(): void
    {
        $this->eligibilitySetup();
        Http::fake();

        $this->assertTrue((new TamaraPaymentService())->checkEligibility(300.0, null));
        $this->assertTrue((new TamaraPaymentService())->checkEligibility(300.0, 'not-a-phone'));

        Http::assertNothingSent();
    }

    public function test_tamara_eligibility_answer_is_cached_but_a_failure_is_not(): void
    {
        $this->eligibilitySetup();
        Http::fake(['*tamara.co/*' => Http::response(['is_eligible' => false], 200)]);
        $service = new TamaraPaymentService();

        $this->assertFalse($service->checkEligibility(300.0, '0500000000'));
        $this->assertFalse($service->checkEligibility(300.0, '0500000000'));
        Http::assertSentCount(1);

        // A different amount is a different question.
        $service->checkEligibility(999.0, '0500000000');
        Http::assertSentCount(2);
    }

    /* Tamara: notification authentication -------------------------------- */

    private function jwt(array $claims, string $secret = 'whsec', string $alg = 'HS256'): string
    {
        $b64 = function ($v) { return rtrim(strtr(base64_encode($v), '+/', '-_'), '='); };
        $h = $b64(json_encode(['typ' => 'JWT', 'alg' => $alg]));
        $p = $b64(json_encode($claims));

        return $h.'.'.$p.'.'.$b64(hash_hmac('sha256', $h.'.'.$p, $secret, true));
    }

    public function test_tamara_accepts_a_valid_hs256_notification_jwt(): void
    {
        config(['services.tamara.notification_token' => 'whsec']);

        $this->assertTrue((new TamaraPaymentService())->verifyNotificationToken(
            $this->jwt(['iss' => 'Tamara', 'exp' => time() + 300])
        ));
    }

    public function test_tamara_rejects_bad_notification_tokens(): void
    {
        config(['services.tamara.notification_token' => 'whsec']);
        $service = new TamaraPaymentService();

        $this->assertFalse($service->verifyNotificationToken($this->jwt(['iss' => 'Tamara'], 'other-secret')), 'wrong secret');
        $this->assertFalse($service->verifyNotificationToken($this->jwt(['iss' => 'Tamara', 'exp' => time() - 10])), 'expired');
        $this->assertFalse($service->verifyNotificationToken($this->jwt(['iss' => 'Tamara'], 'whsec', 'none')), 'alg pinned to HS256');
        $this->assertFalse($service->verifyNotificationToken('a.b.c'), 'garbage');
        $this->assertFalse($service->verifyNotificationToken(''), 'empty');
        $this->assertFalse($service->verifyNotificationToken(null), 'null');

        // Tampered payload with the original signature.
        [$h, $p, $sig] = explode('.', $this->jwt(['iss' => 'Tamara', 'order_id' => 'a']));
        $forged = rtrim(strtr(base64_encode(json_encode(['iss' => 'Tamara', 'order_id' => 'b'])), '+/', '-_'), '=');
        $this->assertFalse($service->verifyNotificationToken($h.'.'.$forged.'.'.$sig), 'tampered payload');
    }

    public function test_tamara_still_accepts_the_raw_token_and_fails_closed_when_unset(): void
    {
        config(['services.tamara.notification_token' => 'whsec']);
        $this->assertTrue((new TamaraPaymentService())->verifyNotificationToken('whsec'));

        config(['services.tamara.notification_token' => '']);
        $this->assertFalse((new TamaraPaymentService())->verifyNotificationToken(''));
        $this->assertFalse((new TamaraPaymentService())->verifyNotificationToken($this->jwt(['iss' => 'Tamara'], '')));
    }
}
