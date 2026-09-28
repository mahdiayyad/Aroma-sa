<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone verification moved to the Tawked Verify API, which generates, sends
 * and checks the one-time code itself. This table therefore holds no code or
 * hash at all — only Tawked's verification id and the result we last saw for
 * it. Dropping otp_codes discards at most a few minutes' worth of pending
 * codes (they expire after 5 minutes).
 */
class ReplaceOtpCodesWithPhoneVerificationsTable extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('otp_codes');

        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('purpose')->default('login');
            $table->string('tawked_id')->unique();
            // pending | verified | expired | failed | superseded
            $table->string('status')->default('pending');
            // nullable() on purpose — see create_email_codes_table: keeps MySQL from auto-adding
            // ON UPDATE CURRENT_TIMESTAMP to the first NOT NULL timestamp column.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('code_hash');
            $table->string('purpose')->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'consumed_at']);
            $table->index('expires_at');
        });
    }
}
