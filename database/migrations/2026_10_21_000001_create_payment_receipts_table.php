<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentReceiptsTable extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            // One receipt per order: the unique index is what makes a
            // duplicate OrderPaid / retried job unable to issue a second one.
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->string('locale', 5)->default('ar');
            $table->string('pdf_path')->nullable();

            // Delivery state. emailed_at is only set after the mail was accepted
            // by the mailer; sending_at is a short-lived claim against
            // concurrent workers sending the same receipt.
            $table->string('emailed_to')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
}
