<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mirrors wishlists exactly — one vote per user per review, toggle/changeable. */
class CreateProductReviewVotesTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_helpful');
            $table->timestamps();

            $table->unique(['product_review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_votes');
    }
}
