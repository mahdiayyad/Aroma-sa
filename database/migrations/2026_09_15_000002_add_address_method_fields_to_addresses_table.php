<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAddressMethodFieldsToAddressesTable extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // How this address was captured: Address::METHOD_NATIONAL_CODE
            // ('national_code') or Address::METHOD_MANUAL ('manual'). A plain
            // nullable string, deliberately NOT a DB-level enum() — see
            // 2026_08_20_000002_make_legacy_address_fields_nullable_on_addresses_table.php:
            // Doctrine DBAL can't introspect MySQL ENUM columns, which broke a
            // later Schema::table(...)->change() on this same table. Nullable,
            // no default: existing rows, and any future lat/lng-only "pinned"
            // row (a method the app no longer asks the shopper to name), are
            // left without one — x-address-summary's existing field-presence
            // inference still renders those exactly as it does today.
            $table->string('method', 20)->nullable()->after('type');

            // Manual-entry-only fields. Nullable: populated only when
            // method = 'manual'; every other row leaves these null forever.
            $table->string('building_number')->nullable()->after('street_address');
            $table->string('apartment_number')->nullable()->after('building_number');
            $table->text('additional_notes')->nullable()->after('formatted_address');

            $table->index('method');
        });

        // Backfill for rows written before this column existed, using the
        // same field-presence inference x-address-summary already performs:
        // - a location_code means it went through the code method.
        // - no code but a street_address means it was typed the old
        //   pre-national-address way — the closest existing analogue to
        //   today's "manual" method.
        // Anything else (pure lat/lng "pinned" rows, or fully empty rows) is
        // deliberately left NULL — it doesn't map cleanly to either of the
        // two methods this feature exposes, and every display surface's
        // existing fallback branch already renders those rows correctly
        // without a method label, exactly as it does today.
        DB::table('addresses')->whereNotNull('location_code')->update(['method' => 'national_code']);
        DB::table('addresses')->whereNull('location_code')->whereNotNull('street_address')->update(['method' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['method']);
            $table->dropColumn(['method', 'building_number', 'apartment_number', 'additional_notes']);
        });
    }
}
