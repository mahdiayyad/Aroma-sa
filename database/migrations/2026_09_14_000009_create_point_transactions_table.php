<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reward-points ledger — the auditable source of truth users.
 * loyalty_points is a denormalized running total of. Every earn/spend is a
 * row here; the balance is never mutated without one. reference_type +
 * reference_id point at whatever caused the transaction (a Referral row,
 * an Order, etc.) without a hard FK, since the reference can be any model.
 */
class CreatePointTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // referral_reward | referral_signup_bonus | redemption | admin_adjustment | refund_reversal | promotional_reward
            $table->integer('points'); // signed: positive = earned, negative = spent
            $table->unsignedInteger('balance_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
}
