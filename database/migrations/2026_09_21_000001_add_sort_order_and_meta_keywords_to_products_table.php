<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Merchandising order (lower first). 0 for everything = today's
            // newest-first listing order, so this is inert until used.
            $table->unsignedInteger('sort_order')->default(0)->after('is_gift_eligible');
            // Free text, comma separated. One column (not per-language): it is
            // rendered as <meta name="keywords"> and imported/exported as-is.
            $table->text('meta_keywords')->nullable()->after('meta_description');

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['sort_order', 'meta_keywords']);
        });
    }
};
