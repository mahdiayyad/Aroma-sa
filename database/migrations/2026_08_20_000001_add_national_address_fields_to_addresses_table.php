<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNationalAddressFieldsToAddressesTable extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // The Saudi National Address short code the shopper types
            // (AAAA1234) plus everything LocationLookupService resolves from
            // it. All nullable/additive: existing rows (typed the old
            // street/city/region way) are untouched and remain valid.
            $table->string('location_code', 8)->nullable()->after('phone');
            $table->string('district')->nullable()->after('region');
            $table->string('country', 2)->nullable()->default('SA')->after('district');
            $table->text('formatted_address')->nullable()->after('postal_code');

            $table->index('location_code');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['location_code']);
            $table->dropColumn(['location_code', 'district', 'country', 'formatted_address']);
        });
    }
}
