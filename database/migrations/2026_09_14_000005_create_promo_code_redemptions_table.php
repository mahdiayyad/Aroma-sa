<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usage ledger — the source of truth for "who used this code, on which
 * order, for how much". promo_code_id is restrictOnDelete (mirrors
 * products.category_id/order_items.product_id elsewhere in this schema):
 * a code with real redemption history can never be hard-deleted, only
 * deactivated/archived (soft delete), so usage history is never orphaned.
 */
class CreatePromoCodeRedemptionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2); // frozen SAR value actually discounted
            $table->timestamps();

            $table->unique(['promo_code_id', 'order_id']);
            $table->index(['promo_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');
    }
}
