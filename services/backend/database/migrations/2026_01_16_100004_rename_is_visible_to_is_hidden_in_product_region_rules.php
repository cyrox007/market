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
        if (!Schema::hasTable('product_region_rules')) {
            return;
        }

        Schema::table('product_region_rules', function (Blueprint $table) {
            // Переименовываем is_visible в is_hidden
            if (Schema::hasColumn('product_region_rules', 'is_visible')) {
                // Сначала добавляем новую колонку
                $table->boolean('is_hidden')
                    ->default(false)
                    ->after('price_modifier_value')
                    ->comment('Скрыть товар в регионе (boolean, default false - показывать)');
            }
        });

        // Копируем данные с инверсией логики: is_visible = false → is_hidden = true
        // Делаем это вне Schema::table, чтобы колонка уже существовала
        if (Schema::hasColumn('product_region_rules', 'is_visible') && Schema::hasColumn('product_region_rules', 'is_hidden')) {
            \DB::statement('UPDATE product_region_rules SET is_hidden = NOT is_visible WHERE is_visible IS NOT NULL');
        }

        // Удаляем старую колонку
        Schema::table('product_region_rules', function (Blueprint $table) {
            if (Schema::hasColumn('product_region_rules', 'is_visible')) {
                $table->dropColumn('is_visible');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('product_region_rules')) {
            return;
        }

        Schema::table('product_region_rules', function (Blueprint $table) {
            if (Schema::hasColumn('product_region_rules', 'is_hidden')) {
                // Возвращаем обратно is_visible
                $table->boolean('is_visible')
                    ->default(true)
                    ->after('price_modifier_value')
                    ->comment('Видимость товара в регионе (boolean, default true)');
            }
        });

        // Копируем данные с инверсией логики: is_hidden = true → is_visible = false
        if (Schema::hasColumn('product_region_rules', 'is_hidden') && Schema::hasColumn('product_region_rules', 'is_visible')) {
            \DB::statement('UPDATE product_region_rules SET is_visible = NOT is_hidden WHERE is_hidden IS NOT NULL');
        }

        // Удаляем новую колонку
        Schema::table('product_region_rules', function (Blueprint $table) {
            if (Schema::hasColumn('product_region_rules', 'is_hidden')) {
                $table->dropColumn('is_hidden');
            }
        });
    }
};
