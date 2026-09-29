<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes emailed to a customer (email + password sign-in, and
 * confirming an email when adding one in Profile). Tawked only handles SMS,
 * so these are ours: hashed at rest (Hash::make(), same as passwords), never
 * stored plain, single-use, attempt-limited.
 */
class CreateEmailCodesTable extends Migration
{
    public function up(): void
    {
        Schema::create('email_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('purpose'); // login | add_credentials
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            // nullable() on purpose: MySQL/MariaDB without explicit_defaults_for_timestamp give the FIRST
            // NOT NULL timestamp column DEFAULT/ON UPDATE CURRENT_TIMESTAMP, so any UPDATE to the row
            // (a wrong-code attempts++) would silently reset the expiry to "now".
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['email', 'purpose', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_codes');
    }
}
