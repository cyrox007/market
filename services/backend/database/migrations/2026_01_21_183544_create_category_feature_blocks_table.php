<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_feature_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->comment('ID категории');
            $table->unsignedBigInteger('feature_block_id')->comment('ID блока фич');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки в категории');
            $table->timestamps();

            $table->unique(['category_id', 'feature_block_id'], 'category_feature_unique');
            $table->index('category_id');
            $table->index('feature_block_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_feature_blocks');
    }
};
