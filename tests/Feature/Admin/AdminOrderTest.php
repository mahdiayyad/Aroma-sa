<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    }

    private function makeOrder(string $status = Order::STATUS_PENDING): Order
    {
        $address = [
            'recipient_name' => 'Sara', 'phone' => '0500000000',
            'street_address' => 'K', 'city' => 'Riyadh', 'region' => 'Riyadh', 'postal_code' => '',
        ];

        return Order::create([
            'order_number'     => 'AR-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status'           => $status,
            'customer_name'    => 'Sara',
            'customer_email'   => 'sara@example.com',
            'customer_phone'   => '0500000000',
            'billing_address'  => $address,
            'shipping_address' => $address,
            'subtotal'         => 200,
            'total_amount'     => 200,
        ]);
    }

    public function test_orders_list_loads(): void
    {
        $this->makeOrder();

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'order_number', 'status', 'total_amount']], 'meta']);
    }

    public function test_order_detail_exposes_allowed_next_transitions(): void
    {
        $order = $this->makeOrder(Order::STATUS_PENDING);

        $this->getJson('/api/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.allowed_next', ['paid', 'cancelled']);
    }

    public function test_valid_status_transition_is_applied(): void
    {
        $order = $this->makeOrder(Order::STATUS_PROCESSING);

        $this->patchJson('/api/admin/orders/'.$order->id.'/status', [
            'status'          => Order::STATUS_SHIPPED,
            'tracking_number' => 'TRK-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.tracking_number', 'TRK-123');

        $this->assertNotNull($order->fresh()->shipped_at);
    }

    public function test_illegal_status_transition_is_rejected(): void
    {
        $order = $this->makeOrder(Order::STATUS_PENDING);

        // pending -> delivered is not allowed
        $this->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => Order::STATUS_DELIVERED])
            ->assertStatus(422);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }
}
