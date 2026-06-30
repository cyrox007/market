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
        Schema::table('orders', function (Blueprint $table) {
            // Добавляем поля для хранения данных о доставке из конфигурации метода доставки
            if (!Schema::hasColumn('orders', 'delivery_days_min')) {
                $table->integer('delivery_days_min')->nullable()->after('delivery_floor')->comment('Минимальный срок доставки из метода доставки');
            }
            if (!Schema::hasColumn('orders', 'delivery_days_max')) {
                $table->integer('delivery_days_max')->nullable()->after('delivery_days_min')->comment('Максимальный срок доставки из метода доставки');
            }
            if (!Schema::hasColumn('orders', 'delivery_base_price')) {
                $table->decimal('delivery_base_price', 10, 2)->nullable()->after('delivery_days_max')->comment('Базовая цена доставки из метода доставки');
            }
            if (!Schema::hasColumn('orders', 'delivery_free_threshold')) {
                $table->decimal('delivery_free_threshold', 10, 2)->nullable()->after('delivery_base_price')->comment('Порог бесплатной доставки из метода доставки');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_days_min')) {
                $table->dropColumn('delivery_days_min');
            }
            if (Schema::hasColumn('orders', 'delivery_days_max')) {
                $table->dropColumn('delivery_days_max');
            }
            if (Schema::hasColumn('orders', 'delivery_base_price')) {
                $table->dropColumn('delivery_base_price');
            }
            if (Schema::hasColumn('orders', 'delivery_free_threshold')) {
                $table->dropColumn('delivery_free_threshold');
            }
        });
    }
};
