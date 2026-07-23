<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('gateway'); // moyasar, tabby, tamara
            $table->string('method'); // mada, applepay, visa, mastercard, etc.
            $table->string('status')->default('pending'); // pending, authorized, captured, failed, refunded

            // Transaction details
            $table->string('transaction_id')->unique()->nullable(); // gateway's transaction ID
            $table->string('reference_number')->nullable(); // order reference from gateway
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SAR');

            // Gateway response (store for debugging)
            $table->json('gateway_response')->nullable();

            // Refund tracking
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
}
