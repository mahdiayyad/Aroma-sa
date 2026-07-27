<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private array $validBilling = [
        'recipient_name' => 'Sara Al Qahtani',
        'email'          => 'sara@example.com',
        'phone'          => '0500000000',
        'street_address' => 'King Fahd Rd',
        'city'           => 'Riyadh',
        'region'         => 'Riyadh',
        'postal_code'    => '12211',
    ];

    private function seedCartAndAddress(): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
    }

    public function test_the_delivery_page_renders(): void
    {
        $this->seedCartAndAddress();

        $this->get(route('checkout.delivery'))
            ->assertOk()
            ->assertSee(__('delivery.title'));
    }

    public function test_a_valid_delivery_selection_is_saved_and_continues_to_order_review(): void
    {
        $this->seedCartAndAddress();
        $date = now()->addDays(3)->toDateString();

        $this->post(route('checkout.delivery.store'), [
            'delivery_date'         => $date,
            'delivery_time_slot'    => Order::DELIVERY_SLOT_AFTERNOON,
            'delivery_instructions' => 'Ring the bell twice',
        ])->assertRedirect(route('checkout.order-review'))->assertSessionHasNoErrors();

        $this->assertSame($date, session('checkout.delivery.date'));
        $this->assertSame(Order::DELIVERY_SLOT_AFTERNOON, session('checkout.delivery.time_slot'));
        $this->assertSame('Ring the bell twice', session('checkout.delivery.instructions'));
    }

    public function test_a_date_before_the_minimum_lead_time_is_rejected(): void
    {
        config(['aroma.delivery.min_lead_days' => 2]);
        $this->seedCartAndAddress();

        $this->post(route('checkout.delivery.store'), [
            'delivery_date'      => now()->addDay()->toDateString(),
            'delivery_time_slot' => Order::DELIVERY_SLOT_MORNING,
        ])->assertSessionHasErrors('delivery_date');
    }

    public function test_a_date_beyond_the_maximum_lead_time_is_rejected(): void
    {
        config(['aroma.delivery.max_lead_days' => 5]);
        $this->seedCartAndAddress();

        $this->post(route('checkout.delivery.store'), [
            'delivery_date'      => now()->addDays(10)->toDateString(),
            'delivery_time_slot' => Order::DELIVERY_SLOT_MORNING,
        ])->assertSessionHasErrors('delivery_date');
    }

    public function test_an_invalid_time_slot_is_rejected(): void
    {
        $this->seedCartAndAddress();

        $this->post(route('checkout.delivery.store'), [
            'delivery_date'      => now()->addDays(2)->toDateString(),
            'delivery_time_slot' => 'midnight',
        ])->assertSessionHasErrors('delivery_time_slot');
    }

    public function test_it_redirects_to_address_when_no_billing_address_is_in_session(): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();

        $this->get(route('checkout.delivery'))
            ->assertRedirect(route('checkout.address'));
    }
}
