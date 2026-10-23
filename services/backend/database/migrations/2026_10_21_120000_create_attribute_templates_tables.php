<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('applied_count')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_template_id')->constrained('attribute_templates')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_template_id', 'attribute_id'], 'attribute_template_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_template_items');
        Schema::dropIfExists('attribute_templates');
    }
};
