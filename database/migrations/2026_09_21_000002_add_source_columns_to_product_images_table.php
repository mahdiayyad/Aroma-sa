<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            // Where an imported image was downloaded from. source_hash (sha1 of
            // the normalised URL) is what re-imports look up, so importing the
            // same sheet twice never downloads or stores the same image twice.
            $table->text('source_url')->nullable()->after('path');
            $table->char('source_hash', 40)->nullable()->after('source_url');

            $table->index(['product_id', 'source_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'source_hash']);
            $table->dropColumn(['source_url', 'source_hash']);
        });
    }
};
