<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery date/time-slot scheduling has been removed from checkout entirely
 * — replaced by a static "delivered within 7 business days" notice. These
 * columns were added by 2026_07_26_000002_add_gift_and_delivery_fields_to_
 * orders_table (which also added unrelated gift columns that must stay).
 */
class RemoveDeliverySchedulingFieldsFromOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_date']);
            $table->dropColumn(['delivery_date', 'delivery_time_slot', 'delivery_instructions']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('greeting_card_id');
            $table->string('delivery_time_slot')->nullable()->after('delivery_date');
            $table->text('delivery_instructions')->nullable()->after('delivery_time_slot');
            $table->index('delivery_date');
        });
    }
}
