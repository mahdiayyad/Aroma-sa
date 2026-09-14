<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unique(user_id, product_id) is the direct DB-level fix for duplicate
 * review attempts — one review per customer per product, enforced, not
 * just checked in application code. Soft-deletes so admin "delete" of an
 * inappropriate review is recoverable, matching Product's own convention.
 */
class CreateProductReviewsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('rating'); // 1-5, app-validated
            $table->string('title')->nullable();
            $table->text('body');
            $table->boolean('is_verified_purchase')->default(false); // frozen at submission

            $table->string('status')->default('pending'); // pending | approved | rejected | hidden
            $table->timestamp('approved_at')->nullable();

            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->unsignedInteger('reported_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
}
