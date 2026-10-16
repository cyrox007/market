<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_transfer_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('draft');
            $table->timestamp('heartbeat_at')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
        Schema::create('catalog_transfer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('catalog_transfer_runs')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->json('mapping');
            $table->string('status')->default('pending');
            $table->unsignedInteger('cursor')->default(1);
            $table->unsignedInteger('total')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();
        });
        Schema::create('catalog_transfer_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('catalog_transfer_runs')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('catalog_transfer_files')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('code', 255)->nullable()->index();
            $table->unsignedBigInteger('old_id')->nullable();
            $table->decimal('price', 16, 2)->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('result')->nullable();
            $table->json('source')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->unique(['file_id', 'row_number']);
            $table->index(['run_id', 'status']);
        });
        Schema::create('catalog_transfer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('catalog_transfer_runs')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('level')->default('info');
            $table->text('message');
            $table->timestamp('created_at');
            $table->index(['run_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_transfer_events');
        Schema::dropIfExists('catalog_transfer_rows');
        Schema::dropIfExists('catalog_transfer_files');
        Schema::dropIfExists('catalog_transfer_runs');
    }
};
