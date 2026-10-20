<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAramexShipmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('aramex_shipments', function (Blueprint $table) {
            $table->id();
            // One Aramex shipment per order; the unique index is also what
            // stops two concurrent "ship" clicks from both creating one.
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('awb_number', 32)->nullable()->unique();
            $table->string('label_path')->nullable();

            // pending | failed | created | in_transit | out_for_delivery | delivered | returned | exception
            $table->string('shipping_status', 32)->default('pending');
            $table->string('status_code', 32)->nullable();
            $table->string('status_description')->nullable();
            $table->timestamp('last_event_at')->nullable();

            $table->string('product_group', 8)->nullable();
            $table->string('product_type', 8)->nullable();
            $table->char('payment_type', 1)->default('P');
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->unsignedSmallInteger('pieces')->default(1);

            $table->text('error_message')->nullable();
            $table->json('history')->nullable();

            $table->timestamps();

            $table->index('shipping_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aramex_shipments');
    }
}
