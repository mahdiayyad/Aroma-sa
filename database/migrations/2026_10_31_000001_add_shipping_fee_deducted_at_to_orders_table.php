<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a refund on this order has already deducted the Aramex
 * shipping fee — set once, on the first refund that actually deducts it, so
 * a second partial refund on the same order never deducts it twice. See
 * Order::suggestedRefundAmount().
 */
class AddShippingFeeDeductedAtToOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('shipping_fee_deducted_at')->nullable()->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_fee_deducted_at');
        });
    }
}
