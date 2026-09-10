<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductOptionValuesTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();

            $table->json('label')->nullable();               // { "ar": "…", "en": "…" }
            $table->decimal('price_delta', 10, 2)->default(0); // added to the line unit price ( ≥ 0 )
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['product_option_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
}
