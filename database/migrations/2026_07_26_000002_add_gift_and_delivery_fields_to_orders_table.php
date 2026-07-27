<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, all-nullable gifting + delivery-scheduling fields for the checkout
 * refactor (see ai-docs analysis). Zero impact on existing rows/queries.
 *
 * Recipient identity is intentionally NOT duplicated here — when an order is a
 * gift, the existing `shipping_address` JSON (recipient_name, phone, ...) IS
 * the recipient's details. See the "Recipient model" decision in the analysis.
 */
class AddGiftAndDeliveryFieldsToOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Gifting
            $table->boolean('is_gift')->default(false)->after('customer_notes');
            $table->text('gift_message')->nullable()->after('is_gift');
            $table->boolean('is_anonymous')->default(false)->after('gift_message');
            $table->decimal('gift_wrap_fee', 10, 2)->default(0)->after('is_anonymous');
            $table->foreignId('greeting_card_id')->nullable()->after('gift_wrap_fee')
                ->constrained('gift_cards')->nullOnDelete();

            // Delivery scheduling (data capture only — no carrier/slot-capacity
            // logic yet; fulfilled manually via the admin. See analysis §7.)
            $table->date('delivery_date')->nullable()->after('greeting_card_id');
            $table->string('delivery_time_slot')->nullable()->after('delivery_date'); // morning|afternoon|evening
            $table->text('delivery_instructions')->nullable()->after('delivery_time_slot');

            $table->index('is_gift');
            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('greeting_card_id');
            $table->dropColumn([
                'is_gift', 'gift_message', 'is_anonymous', 'gift_wrap_fee',
                'delivery_date', 'delivery_time_slot', 'delivery_instructions',
            ]);
        });
    }
}
