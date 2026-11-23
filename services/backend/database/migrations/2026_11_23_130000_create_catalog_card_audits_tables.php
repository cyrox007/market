<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_card_audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 16)->index();
            $table->unsignedInteger('checked_count')->default(0);
            $table->unsignedInteger('affected_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('files_checked_at')->nullable();
            $table->string('error', 250)->nullable();
        });

        Schema::create('catalog_card_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('catalog_card_audit_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('critical_count')->default(0)->index();
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->json('reasons');
            $table->timestamp('checked_at');
            $table->unique(['run_id', 'product_id']);
            $table->index(['run_id', 'critical_count', 'warning_count'], 'catalog_card_issues_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_card_issues');
        Schema::dropIfExists('catalog_card_audit_runs');
    }
};
