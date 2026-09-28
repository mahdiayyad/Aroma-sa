<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer-initiated refund request/ticket — admin reviews and marks it
 * approved/rejected here; actually moving money still goes through the
 * existing gateway-specific admin action (e.g. Order::tamaraRefund), kept
 * separate on purpose so this table stays a simple request/decision log,
 * not a payment-operations record.
 */
class CreateRefundRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reason');
            $table->text('notes')->nullable();

            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
}
