<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('categories')->nullOnDelete();

            $table->json('name');                 // { "ar": "...", "en": "..." }
            $table->string('slug')->unique();
            $table->json('description')->nullable();

            $table->string('image')->nullable();  // S3 path
            $table->string('icon')->nullable();   // bootstrap-icon class for nav tiles

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // SEO (bilingual)
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
}
