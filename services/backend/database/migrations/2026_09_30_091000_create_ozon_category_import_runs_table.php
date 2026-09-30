<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ozon_category_import_runs')) {
            return;
        }

        Schema::create('ozon_category_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('source_filename')->nullable();
            $table->string('stored_path');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_products')->default(0);
            $table->unsignedInteger('updated_products')->default(0);
            $table->unsignedInteger('created_parents')->default(0);
            $table->unsignedInteger('created_variants')->default(0);
            $table->unsignedInteger('updated_variants')->default(0);
            $table->unsignedInteger('images_queued')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->json('summary')->nullable();
            $table->json('errors')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ozon_category_import_runs');
    }
};
