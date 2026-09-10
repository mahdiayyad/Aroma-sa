<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductOptionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Machine key ('closure_style', 'sleeve_style', …) — stable across
            // label renames, and what cart/order snapshots record.
            $table->string('key');
            $table->json('label')->nullable();        // { "ar": "…", "en": "…" } — admin-renameable
            $table->string('type')->default('select'); // reserved for future non-select inputs

            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->unique(['product_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
}
