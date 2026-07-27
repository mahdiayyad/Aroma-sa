<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed greeting card catalogue. Single catalogue shared by the
 * checkout Gift Options step and the (future) cart-based gift studio — see
 * ai-docs checkout refactor analysis for why this must not be duplicated.
 */
class CreateGiftCardsTable extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();

            $table->json('name');          // { "ar": "...", "en": "..." }
            $table->string('slug')->unique();
            $table->string('image');       // S3/public disk path — the card artwork

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
}
