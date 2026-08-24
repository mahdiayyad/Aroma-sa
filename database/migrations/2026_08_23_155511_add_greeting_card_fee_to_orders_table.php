<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGreetingCardFeeToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Snapshot of the chosen card's price at order time (like
            // gift_wrap_fee) — so a later price change on the gift_cards
            // catalogue never rewrites what a past order actually charged.
            $table->decimal('greeting_card_fee', 10, 2)->default(0)->after('greeting_card_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('greeting_card_fee');
        });
    }
}
