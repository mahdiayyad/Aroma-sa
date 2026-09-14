<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductReviewReportsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['product_review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_reports');
    }
}
