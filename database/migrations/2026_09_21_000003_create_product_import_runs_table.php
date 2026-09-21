<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // pending | queued | validating | invalid | ready | applying | completed | failed | cancelled
            $table->string('status', 20)->default('pending');
            $table->string('phase', 20)->nullable();

            $table->string('original_name');
            $table->string('path');                        // private storage, imports/{id}/source.xlsx

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);

            $table->json('options')->nullable();           // auto_apply, replace_images, ignore_image_failures
            $table->json('summary')->nullable();           // created, updated, unchanged, images_added, ...
            $table->json('errors')->nullable();            // [{row, column, message, value}] (capped)
            $table->unsignedInteger('error_count')->default(0);
            $table->json('warnings')->nullable();
            $table->text('message')->nullable();           // failure / rollback explanation

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_runs');
    }
};
