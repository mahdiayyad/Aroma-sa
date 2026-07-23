<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            $table->json('name');
            $table->string('slug')->unique();
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();

            $table->string('sku')->nullable()->unique();

            // Pricing (SAR). base_price is the display/"from" price; variants may
            // carry their own absolute price. compare_at_price drives the strike-through.
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('SAR');

            // Inventory for simple (variant-less) products.
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('has_variants')->default(false);

            // Merchandising / brand attributes.
            $table->string('scent_family')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            $table->boolean('is_gift_eligible')->default(true);

            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_featured']);
            $table->index(['is_active', 'is_new_arrival']);
            $table->index('category_id');
            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
}
