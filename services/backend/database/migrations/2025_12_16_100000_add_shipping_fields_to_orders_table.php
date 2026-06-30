<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Добавляет поля для работы с новой системой доставки через локации
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Связь с локацией доставки
            if (!Schema::hasColumn('orders', 'shipping_location_id')) {
                $table->foreignId('shipping_location_id')->nullable()->constrained('shipping_locations')->onDelete('set null');
                $table->index('shipping_location_id');
            }

            // Связь с shipping method (Vanilo)
            if (!Schema::hasColumn('orders', 'shipping_method_id')) {
                $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->onDelete('set null');
                $table->index('shipping_method_id');
            }

            // Тип обработки доставки (разгрузка, подъем)
            if (!Schema::hasColumn('orders', 'delivery_handling_type_id')) {
                $table->foreignId('delivery_handling_type_id')->nullable()->constrained('delivery_handling_types')->onDelete('set null');
            }

            // Этаж для доставки (если требуется)
            if (!Schema::hasColumn('orders', 'delivery_floor')) {
                $table->integer('delivery_floor')->nullable()->comment('Этаж доставки (для ручного подъема)');
            }

            // Требуется ли сборка
            if (!Schema::hasColumn('orders', 'requires_assembly')) {
                $table->boolean('requires_assembly')->default(false)->comment('Требуется ли сборка мебели');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipping_location_id')) {
                $table->dropForeign(['shipping_location_id']);
                $table->dropColumn('shipping_location_id');
            }

            if (Schema::hasColumn('orders', 'shipping_method_id')) {
                $table->dropForeign(['shipping_method_id']);
                $table->dropColumn('shipping_method_id');
            }

            if (Schema::hasColumn('orders', 'delivery_handling_type_id')) {
                $table->dropForeign(['delivery_handling_type_id']);
                $table->dropColumn('delivery_handling_type_id');
            }

            if (Schema::hasColumn('orders', 'delivery_floor')) {
                $table->dropColumn('delivery_floor');
            }

            if (Schema::hasColumn('orders', 'requires_assembly')) {
                $table->dropColumn('requires_assembly');
            }
        });
    }
};
