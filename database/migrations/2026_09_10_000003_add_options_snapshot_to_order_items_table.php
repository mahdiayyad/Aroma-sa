<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOptionsSnapshotToOrderItemsTable extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Denormalised copy of the customisation options chosen for this
            // line (closure style, …) — same delete-safe pattern as
            // variant_data, so the order still reads correctly after the
            // product's options change or the product is removed.
            $table->json('options_snapshot')->nullable()->after('variant_data');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('options_snapshot');
        });
    }
}
