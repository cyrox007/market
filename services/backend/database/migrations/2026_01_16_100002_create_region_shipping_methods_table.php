<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('region_shipping_methods')) {
            return;
        }

        Schema::create('region_shipping_methods', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('shipping_location_id')
                ->constrained('shipping_locations')
                ->onDelete('cascade')
                ->comment('ID региона');
            
            $table->foreignId('shipping_method_id')
                ->constrained('shipping_methods')
                ->onDelete('cascade')
                ->comment('ID метода доставки (Vanilo)');
            
            $table->boolean('is_active')->default(true)->comment('Активность');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();
            
            // Индексы (используем короткие имена для совместимости с MySQL)
            $table->unique(['shipping_location_id', 'shipping_method_id'], 'rsm_location_method_unique');
            $table->index(['shipping_location_id', 'is_active'], 'rsm_location_active_idx');
            $table->index('sort_order', 'rsm_sort_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('region_shipping_methods');
    }
};
