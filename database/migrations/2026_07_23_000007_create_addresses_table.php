<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddressesTable extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['billing', 'shipping']);
            $table->string('label')->nullable(); // "Home", "Office", etc.
            $table->string('recipient_name');
            $table->string('phone');
            $table->string('city');
            $table->string('region');
            $table->text('street_address');
            $table->string('postal_code')->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
}
