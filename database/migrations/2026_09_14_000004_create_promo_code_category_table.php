<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Category-restricted promo codes — DiscountCalculator discounts only line items whose product falls in one of these categories. */
class CreatePromoCodeCategoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promo_code_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_category');
    }
}
