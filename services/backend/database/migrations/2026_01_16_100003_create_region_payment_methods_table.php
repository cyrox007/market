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
        if (Schema::hasTable('region_payment_methods')) {
            return;
        }

        Schema::create('region_payment_methods', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('shipping_location_id')
                ->constrained('shipping_locations')
                ->onDelete('cascade')
                ->comment('ID региона');
            
            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->onDelete('cascade')
                ->comment('ID метода оплаты');
            
            $table->boolean('is_active')->default(true)->comment('Активность');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();
            
            // Индексы (используем короткие имена для совместимости с MySQL)
            $table->unique(['shipping_location_id', 'payment_method_id'], 'rpm_location_method_unique');
            $table->index(['shipping_location_id', 'is_active'], 'rpm_location_active_idx');
            $table->index('sort_order', 'rpm_sort_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('region_payment_methods');
    }
};
