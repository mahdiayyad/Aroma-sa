<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeLegacyAddressFieldsNullableOnAddressesTable extends Migration
{
    public function up(): void
    {
        // street_address/city/region are now resolved server-side from a
        // location_code lookup rather than typed by the shopper, so they can
        // no longer be guaranteed at write time (e.g. a legacy read path or
        // a future provider response that omits one). Raw SQL rather than
        // Blueprint::change(): this project doesn't depend on doctrine/dbal,
        // and `addresses.type` is an enum column — Doctrine's schema
        // introspection throws "Unknown database type enum" changing ANY
        // column on a table that contains one, unless a type mapping is
        // registered first. Plain ALTER TABLE avoids both the new dependency
        // and that landmine.
        DB::statement('ALTER TABLE addresses MODIFY COLUMN street_address TEXT NULL');
        DB::statement('ALTER TABLE addresses MODIFY COLUMN city VARCHAR(255) NULL');
        DB::statement('ALTER TABLE addresses MODIFY COLUMN region VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Deliberately a no-op. Once any row has been saved post-cutover
        // with these columns null (which is now the normal case), reverting
        // to NOT NULL would fail outright without first backfilling nulls to
        // '' — a lossy rewrite of real data that isn't safe to do silently
        // inside a migration rollback.
    }
}
