<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_shipping_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('shipping_location_id')->constrained('shipping_locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['warehouse_id', 'shipping_location_id'], 'warehouse_location_unique');
        });

        Schema::create('product_warehouse_stocks', function (Blueprint $table) {
            $table->id();
            // products.id в проекте = INT UNSIGNED (не BIGINT), поэтому foreignId тут несовместим
            $table->unsignedInteger('product_id');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id'], 'product_warehouse_unique');
            $table->index('warehouse_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warehouse_stocks');
        Schema::dropIfExists('warehouse_shipping_location');
        Schema::dropIfExists('warehouses');
    }
};
