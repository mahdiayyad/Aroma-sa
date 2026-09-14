<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralFieldsToUsersTable extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable+unique mirrors email/phone's own style on this table.
            // Generated once at registration (ReferralService::generateCode())
            // and never changes — never mass-assignable (not in User::$fillable).
            $table->string('referral_code')->nullable()->unique()->after('role');

            // Frozen pointer to who referred this user, set once at
            // registration. The `referrals` table is the richer,
            // status-tracking record of the same relationship; this column
            // is the fast denormalized lookup (same "frozen pointer + full
            // relationship row" shape as orders.greeting_card_id + GiftCard).
            $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
            $table->dropColumn('referral_code');
        });
    }
}
