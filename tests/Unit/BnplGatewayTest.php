<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Payment\TabbyPaymentService;
use App\Services\Payment\TamaraPaymentService;
use Illuminate\Support\Collection;
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
}
