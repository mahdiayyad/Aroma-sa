<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPromoCodeToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('discount_amount')
                ->constrained()->nullOnDelete();

            // Frozen snapshot of the code text itself (same reasoning as
            // gift_card_to/gift_card_from) — a past order always displays
            // what code was used even if the promo_codes row is later
            // renamed or archived.
            $table->string('promo_code')->nullable()->after('promo_code_id');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn('promo_code');
        });
    }
}
