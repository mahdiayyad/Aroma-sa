<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoordinatesToAddressesTable extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Set by the map picker (drag pin / search / "use my location") so
            // delivery can route to the exact point instead of the typed street
            // text alone. Nullable: addresses saved before this feature, or
            // typed without touching the map, still work.
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
}
