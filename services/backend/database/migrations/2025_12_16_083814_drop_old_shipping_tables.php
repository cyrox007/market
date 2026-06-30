<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Удаляем старые таблицы, которые были заменены новой единой моделью ShippingLocation
     */
    public function up(): void
    {
        // Удаляем старую таблицу связи (если существует)
        if (Schema::hasTable('locality_delivery_handling')) {
            Schema::dropIfExists('locality_delivery_handling');
        }

        // Удаляем старые таблицы иерархии (если существуют)
        if (Schema::hasTable('localities')) {
            Schema::dropIfExists('localities');
        }
        if (Schema::hasTable('regions')) {
            Schema::dropIfExists('regions');
        }
        if (Schema::hasTable('federal_districts')) {
            Schema::dropIfExists('federal_districts');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Не восстанавливаем старые таблицы
    }
};
