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
        Schema::create('category_delivery_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->comment('ID категории');
            $table->unsignedBigInteger('delivery_block_id')->comment('ID блока доставки');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки в категории');
            $table->timestamps();

            $table->unique(['category_id', 'delivery_block_id'], 'category_delivery_unique');
            $table->index('category_id');
            $table->index('delivery_block_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_delivery_blocks');
    }
};
