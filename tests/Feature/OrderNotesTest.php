<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNotesTest extends TestCase
{
    use RefreshDatabase;

    private array $address = [
        'recipient_name' => 'Sara', 'phone' => '0500000000',
        'street_address' => 'King Fahd Rd', 'city' => 'Riyadh', 'region' => 'Riyadh', 'postal_code' => '',
    ];

    private function order(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'AR-2026-00'.random_int(1000, 9999),
            'status' => Order::STATUS_PAID,
            'customer_name' => 'Sara', 'customer_email' => 'sara@example.com', 'customer_phone' => '0500000000',
            'billing_address' => $this->address, 'shipping_address' => $this->address,
            'subtotal' => 200, 'total_amount' => 200,
        ], $overrides));

        $order->items()->create([
            'product_id' => \App\Models\Product::factory()->create()->id,
            'product_data' => ['name' => 'Noir Abaya', 'image' => null, 'sku' => 'ABY-1'],
            'variant_data' => ['ar' => 'مقاس M', 'en' => 'Size M'],
            'unit_price' => 200, 'quantity' => 1, 'line_total' => 200,
        ]);

        return $order->load('items');
    }

    public function test_customer_order_detail_shows_the_note(): void
    {
        $user = User::factory()->create();
        $order = $this->order(['user_id' => $user->id, 'customer_notes' => 'Please call before delivery']);

        $this->actingAs($user)->get(route('order.show', $order))
            ->assertOk()
            ->assertSee('Please call before delivery');
    }

    public function test_customer_order_detail_shows_the_variant_label(): void
    {
        // Regression: the variant sub-line read variant_data['name'] (never
        // present — the array is { ar, en }) and rendered blank.
        $user = User::factory()->create();
        $order = $this->order(['user_id' => $user->id]);

        $this->withSession(['locale' => 'en'])->actingAs($user)->get(route('order.show', $order))
            ->assertOk()
            ->assertSee('Size M');
    }

    public function test_confirmation_email_includes_the_note_gift_message_and_variant_label(): void
    {
        $this->app->setLocale('en');
        $order = $this->order(['customer_notes' => 'Gate code 4457', 'is_gift' => true, 'gift_message' => 'Congrats!']);

        $html = (new OrderConfirmationMail($order))->render();

        $this->assertStringContainsString('Gate code 4457', $html);
        $this->assertStringContainsString('Congrats!', $html);
        $this->assertStringContainsString('Size M', $html);
    }

    public function test_notes_are_stripped_of_control_characters_at_checkout(): void
    {
        $product = \App\Models\Product::factory()->create(['stock_quantity' => 5]);
        $this->post('/cart', ['product_id' => $product->id]);

        $this->followingRedirects()->post(route('checkout.address.store'), [
            'billing_address' => [
                'recipient_name' => 'Sara',
                'email' => 'sara@example.com',
                'phone' => '+966500000000',
                'latitude' => '24.7',
                'longitude' => '46.6',
            ],
            'customer_notes' => "Leave at\x00 reception\x07",
        ]);

        $this->assertSame('Leave at reception', session('checkout.customer_notes'));
    }
}
