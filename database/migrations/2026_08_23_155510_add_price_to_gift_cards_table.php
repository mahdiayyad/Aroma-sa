<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPriceToGiftCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            // Flat per-design fee added to the order when this card is
            // chosen — 0 for a free design (e.g. the blank note), 5.00 SAR
            // by default for the rest. Admin-editable, not config-driven,
            // since it can now vary per card.
            $table->decimal('price', 8, 2)->default(5.00)->after('image');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
}
