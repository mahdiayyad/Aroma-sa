<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Order identification
            $table->string('order_number')->unique(); // AR-2026-000042
            $table->string('status')->default('pending'); // pending, paid, processing, shipped, delivered, cancelled

            // Customer info (snapshot at time of order)
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');

            // Addresses (snapshot at time of order)
            $table->json('billing_address');
            $table->json('shipping_address');

            // Pricing (in SAR)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // Fulfillment
            $table->string('shipping_method')->nullable(); // standard, express, etc.
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Notes
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}
