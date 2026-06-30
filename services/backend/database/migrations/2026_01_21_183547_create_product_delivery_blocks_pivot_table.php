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
        Schema::create('product_delivery_blocks_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('ID товара');
            $table->unsignedBigInteger('delivery_block_id')->comment('ID блока доставки');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки в товаре');
            $table->timestamps();

            $table->unique(['product_id', 'delivery_block_id'], 'product_delivery_unique');
            $table->index('product_id');
            $table->index('delivery_block_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_delivery_blocks_pivot');
    }
};
