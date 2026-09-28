<?php

namespace Tests\Feature;

use App\Mail\RefundRequestSubmittedMail;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RefundRequestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function paidOrderFor(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => 'AR-2026-00'.random_int(1000, 9999),
            'status' => Order::STATUS_PAID,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '0500000000',
            'billing_address' => ['recipient_name' => $user->name],
            'shipping_address' => ['recipient_name' => $user->name],
            'subtotal' => 100,
            'total_amount' => 100,
        ]);
    }

    public function test_a_customer_can_request_a_refund_for_their_own_paid_order(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $order = $this->paidOrderFor($user);

        $this->actingAs($user)
            ->post(route('account.refund-requests.store'), [
                'order_id' => $order->id,
                'reason' => 'damaged',
                'notes' => 'The box was crushed.',
            ])
            ->assertRedirect(route('order.show', $order))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('refund_requests', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => 'damaged',
            'status' => RefundRequest::STATUS_PENDING,
        ]);

        Mail::assertSent(RefundRequestSubmittedMail::class);
    }

    public function test_a_customer_cannot_request_a_refund_for_someone_elses_order(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $order = $this->paidOrderFor($owner);

        $this->actingAs($stranger)
            ->post(route('account.refund-requests.store'), [
                'order_id' => $order->id,
                'reason' => 'damaged',
            ])
            ->assertSessionHasErrors('order_id');

        $this->assertDatabaseCount('refund_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_a_refund_cannot_be_requested_for_an_unpaid_order(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $order = $this->paidOrderFor($user);
        $order->update(['status' => Order::STATUS_PENDING]);

        $this->actingAs($user)
            ->post(route('account.refund-requests.store'), [
                'order_id' => $order->id,
                'reason' => 'damaged',
            ])
            ->assertSessionHasErrors('order_id');

        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_a_second_request_is_blocked_while_one_is_already_pending(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $order = $this->paidOrderFor($user);

        $this->actingAs($user)->post(route('account.refund-requests.store'), [
            'order_id' => $order->id, 'reason' => 'damaged',
        ]);

        $this->actingAs($user)->post(route('account.refund-requests.store'), [
            'order_id' => $order->id, 'reason' => 'other',
        ])->assertRedirect(route('order.show', $order));

        $this->assertDatabaseCount('refund_requests', 1);
    }

    public function test_an_admin_can_view_the_refund_requests_list(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $order = $this->paidOrderFor($user);
        RefundRequest::create(['order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'damaged', 'notes' => 'Box was crushed']);

        $this->actingAs($admin)
            ->get(route('admin.refund-requests.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(__('refund.reasons.damaged'));
    }

    public function test_an_admin_can_approve_a_refund_request(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $order = $this->paidOrderFor($user);
        $refundRequest = RefundRequest::create(['order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'damaged']);

        $this->actingAs($admin)
            ->patch(route('admin.refund-requests.approve', $refundRequest))
            ->assertRedirect();

        $this->assertSame(RefundRequest::STATUS_APPROVED, $refundRequest->fresh()->status);
        $this->assertNotNull($refundRequest->fresh()->resolved_at);
    }

    public function test_an_admin_can_reject_a_refund_request(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $order = $this->paidOrderFor($user);
        $refundRequest = RefundRequest::create(['order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'other']);

        $this->actingAs($admin)
            ->patch(route('admin.refund-requests.reject', $refundRequest))
            ->assertRedirect();

        $this->assertSame(RefundRequest::STATUS_REJECTED, $refundRequest->fresh()->status);
    }
}
