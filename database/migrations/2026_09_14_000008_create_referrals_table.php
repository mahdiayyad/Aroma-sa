<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The status-tracking record of a referral relationship (users.
 * referred_by_user_id is the fast denormalized pointer to the same fact).
 * unique(referred_id) is the primary anti-duplicate-reward guard: a user
 * can be referred at most once, ever, enforced at the database level so
 * even a replayed/concurrent registration request can't grant a second
 * reward for the same referred user.
 */
class CreateReferralsTable extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('rewarded'); // pending | rewarded | reversed
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index('referrer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
}
