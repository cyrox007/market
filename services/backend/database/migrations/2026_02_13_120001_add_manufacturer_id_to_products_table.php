<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Связь товара с производителем: заполняется при импорте из 1С и вручную в админке.
 * Дублируется атрибутом «Производитель» для вывода в карточке; manufacturer_id используется для фильтров.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'manufacturer_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('manufacturer_id')
                    ->nullable()
                    ->after('parent_product_id')
                    ->constrained('manufacturers')
                    ->nullOnDelete()
                    ->comment('Производитель: импорт 1С + фильтры в админке/API');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'manufacturer_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['manufacturer_id']);
            });
        }
    }
};
