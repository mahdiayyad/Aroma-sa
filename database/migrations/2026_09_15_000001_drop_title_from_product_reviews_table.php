<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews were simplified to exactly two customer-facing fields — a star
 * rating and a comment. The title field never shipped to real customers
 * (this whole feature was built and simplified within the same session),
 * so there's no data to preserve.
 */
class DropTitleFromProductReviewsTable extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->string('title')->nullable()->after('rating');
        });
    }
}
