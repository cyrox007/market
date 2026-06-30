<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('importer_class');
            $table->string('status', 20); // running, success, failed
            $table->unsignedInteger('created_categories')->default(0);
            $table->unsignedInteger('updated_categories')->default(0);
            $table->unsignedInteger('created_products')->default(0);
            $table->unsignedInteger('updated_products')->default(0);
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('catalog_import_runs', function (Blueprint $table) {
            $table->index('importer_class');
            $table->index(['importer_class', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_runs');
    }
};
