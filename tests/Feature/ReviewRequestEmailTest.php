<?php

namespace Tests\Feature;

use App\Mail\ReviewRequestMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ReviewRequestEmailTest extends TestCase
{
    use RefreshDatabase;

    private function shippedOrder(?User $user, Product $product): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user ? $user->id : null,
            'status' => Order::STATUS_SHIPPED,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
            'product_data' => ['name' => 'x'],
        ]);

        return $order;
    }

    private function deliver(Order $order): void
    {
        app(OrderStatusService::class)->transition($order, Order::STATUS_DELIVERED);
    }

    public function test_delivery_emails_the_customer_a_link_to_review_each_product(): void
    {
        Mail::fake();
        $product = Product::factory()->create();
        $order = $this->shippedOrder(User::factory()->create(), $product);

        $this->deliver($order);

        Mail::assertSent(ReviewRequestMail::class, function (ReviewRequestMail $mail) use ($order, $product) {
            return $mail->hasTo($order->customer_email) && $mail->products->contains($product);
        });
    }

    public function test_the_email_renders_in_the_language_of_the_order(): void
    {
        $product = Product::factory()->create(['name' => ['ar' => 'عباية سوداء', 'en' => 'Black Abaya']]);
        $order = $this->shippedOrder(User::factory()->create(), $product);
        $order->update(['locale' => 'en']);

        $html = (new ReviewRequestMail($order, collect([$product])))->render();

        $this->assertStringContainsString('Black Abaya', $html);
        $this->assertStringContainsString(route('product.show', ['en', $product->slug]).'#reviews', $html);
        $this->assertStringNotContainsString('عباية سوداء', $html);
    }

    public function test_products_the_customer_already_reviewed_are_left_out(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = Product::factory()->create();
        ProductReview::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->deliver($this->shippedOrder($user, $product));

        Mail::assertNothingSent();
    }

    public function test_guest_orders_get_no_email_because_reviews_need_an_account(): void
    {
        Mail::fake();

        $this->deliver($this->shippedOrder(null, Product::factory()->create()));

        Mail::assertNothingSent();
    }

    public function test_other_status_changes_send_nothing(): void
    {
        Mail::fake();
        $order = $this->shippedOrder(User::factory()->create(), Product::factory()->create());
        $order->update(['status' => Order::STATUS_PROCESSING]);

        app(OrderStatusService::class)->transition($order, Order::STATUS_SHIPPED);

        Mail::assertNotSent(ReviewRequestMail::class);
    }

    public function test_a_mail_failure_does_not_block_the_status_change(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('mail server down'));
        $order = $this->shippedOrder(User::factory()->create(), Product::factory()->create());

        $this->deliver($order);

        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
    }
}
