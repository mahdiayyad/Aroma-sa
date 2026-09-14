<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed promo code catalogue. Restriction types (customer/product/
 * category) live in pivot tables since they're inherently multi-valued;
 * everything else is a concrete flat column rather than a generic rule
 * engine — matches this app's own preference for explicit columns over EAV
 * (see Order/GiftCard). Soft-deletes so "archive a code" never breaks a
 * past order's frozen promo_code_id reference.
 */
class CreatePromoCodesTable extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique(); // stored uppercase, matched case-insensitively
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('discount_type'); // percentage | fixed
            $table->decimal('discount_value', 8, 2);
            $table->boolean('free_shipping')->default(false);

            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // caps a percentage discount's SAR value

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unsignedInteger('usage_limit')->nullable();             // total uses, all customers
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);              // denormalized, incremented atomically on redemption

            $table->boolean('first_order_only')->default(false);
            $table->boolean('customer_restricted')->default(false); // true => only pivot-listed customers may use it

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
}
