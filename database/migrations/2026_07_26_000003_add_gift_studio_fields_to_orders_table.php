<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The remaining gift-studio fields (card salutation, signature, media link) —
 * additive/nullable, same as the earlier gift/delivery migration.
 */
class AddGiftStudioFieldsToOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gift_card_to')->nullable()->after('gift_message');   // card salutation, may differ from the formal recipient name
            $table->string('gift_card_from')->nullable()->after('gift_card_to'); // blank when is_anonymous
            $table->string('gift_signature')->nullable()->after('gift_card_from'); // public-disk path to the hand-drawn signature PNG
            $table->string('gift_media_url')->nullable()->after('gift_signature'); // pasted song/video link
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['gift_card_to', 'gift_card_from', 'gift_signature', 'gift_media_url']);
        });
    }
}
