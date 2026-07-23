<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Phone-first (KSA norm) — either email or phone identifies the user.
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            // Nullable so social-only accounts (no password) are valid.
            $table->string('password')->nullable();

            // Profile
            $table->string('gender')->nullable();       // female | male | unspecified
            $table->date('dob')->nullable();            // enables birthday offers
            $table->string('locale', 5)->default('ar');
            $table->string('avatar')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->boolean('is_active')->default(true);

            // Social login
            $table->string('provider')->nullable();     // google | apple
            $table->string('provider_id')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
