<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewsSummaryToProductsTable extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Denormalized, kept in sync transactionally by ReviewService
            // whenever a review is approved/rejected/hidden/deleted — so the
            // PDP and listing/card views never need a live aggregate query.
            $table->decimal('reviews_avg_rating', 3, 2)->default(0)->after('is_gift_eligible');
            $table->unsignedInteger('reviews_count')->default(0)->after('reviews_avg_rating');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reviews_avg_rating', 'reviews_count']);
        });
    }
}
