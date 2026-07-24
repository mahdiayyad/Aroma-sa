<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cart;
    private CheckoutService $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cart = new CartService();
        $this->cart->clear();
        // The service reads the cart from the session, so any CartService
        // instance sharing this session sees the same rows.
        $this->checkout = new CheckoutService($this->cart);
    }

    private array $address = [
        'recipient_name' => 'Sara Al Qahtani',
        'phone'          => '0500000000',
        'street_address' => 'King Fahd Rd',
        'city'           => 'Riyadh',
        'region'         => 'Riyadh',
        'postal_code'    => '12211',
    ];

    /* validateCart -------------------------------------------------------- */

    public function test_validate_fails_on_an_empty_cart(): void
    {
        $result = $this->checkout->validateCart();

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_validate_fails_when_a_product_is_out_of_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->cart->add($product->id, null, 2);

        // Sell out after it was added to the cart.
        $product->update(['stock_quantity' => 0]);

        $result = $this->checkout->validateCart();

        $this->assertFalse($result['valid']);
    }

    public function test_validate_passes_with_sufficient_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->cart->add($product->id, null, 2);

        $this->assertTrue($this->checkout->validateCart()['valid']);
    }

    /* calculateTotals ----------------------------------------------------- */

    public function test_it_calculates_totals_from_the_cart(): void
    {
        $a = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 10]);
        $b = Product::factory()->create(['base_price' => 40, 'stock_quantity' => 10]);
        $this->cart->add($a->id, null, 2); // 200
        $this->cart->add($b->id, null, 1); // 40

        $totals = $this->checkout->calculateTotals();

        $this->assertEquals(240, $totals['subtotal']);
        $this->assertEquals(0, $totals['discount_amount']);
        $this->assertEquals(0, $totals['tax_amount']);
        $this->assertEquals(0, $totals['shipping_cost']);
        $this->assertEquals(240, $totals['total_amount']);
    }

    /* createOrder --------------------------------------------------------- */

    public function test_it_creates_an_order_with_items_and_totals(): void
    {
        $this->app->setLocale('en');

        $product = Product::factory()->create([
            'name'           => ['en' => 'Rose Oud', 'ar' => 'ورد عود'],
            'base_price'     => 300,
            'stock_quantity' => 10,
        ]);
        $this->cart->add($product->id, null, 2);

        $order = $this->checkout->createOrder(
            null,
            $this->address,
            [],
            'Sara Al Qahtani',
            'sara@example.com',
            '0500000000',
            'Leave at reception'
        );

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(600, $order->total_amount);
        $this->assertSame('Leave at reception', $order->customer_notes);

        // Blank shipping address falls back to the billing address.
        $this->assertSame($this->address['city'], $order->shipping_address['city']);

        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame(2, $item->quantity);
        $this->assertEquals(600, $item->line_total);
        // The snapshot stores the name resolved in the active locale (a string).
        $this->assertSame('Rose Oud', $item->product_data['name']);
    }

    public function test_order_numbers_are_unique_and_sequential(): void
    {
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 50]);

        $this->cart->add($product->id, null, 1);
        $first = $this->checkout->createOrder(null, $this->address, [], 'A', 'a@example.com', '0500000000');

        $this->cart->clear();
        $this->cart->add($product->id, null, 1);
        $second = $this->checkout->createOrder(null, $this->address, [], 'B', 'b@example.com', '0500000001');

        $year = now()->year;
        $this->assertSame(sprintf('AR-%d-000001', $year), $first->order_number);
        $this->assertSame(sprintf('AR-%d-000002', $year), $second->order_number);
    }

    /* payment ------------------------------------------------------------- */

    public function test_it_creates_a_pending_payment_for_an_order(): void
    {
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 10]);
        $this->cart->add($product->id, null, 1);
        $order = $this->checkout->createOrder(null, $this->address, [], 'A', 'a@example.com', '0500000000');

        $payment = $this->checkout->createPayment($order, 'moyasar', 'mada');

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('SAR', $payment->currency);
        $this->assertEquals($order->total_amount, $payment->amount);
    }

    public function test_marking_an_order_as_paid_captures_the_payment(): void
    {
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 10]);
        $this->cart->add($product->id, null, 1);
        $order = $this->checkout->createOrder(null, $this->address, [], 'A', 'a@example.com', '0500000000');
        $payment = $this->checkout->createPayment($order, 'moyasar', 'mada');

        $this->checkout->markOrderAsPaid($order, $payment);

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
    }
}
