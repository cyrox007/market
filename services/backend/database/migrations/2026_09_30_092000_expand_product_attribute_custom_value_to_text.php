<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('product_product_attributes')
            || ! Schema::hasColumn('product_product_attributes', 'custom_value')) {
            return;
        }

        // Ozon содержит характеристики с многострочными значениями длиннее 255 символов
        // (например, описание нескольких упаковок). STRING для таких данных недостаточен.
        if (Schema::hasIndex('product_product_attributes', 'product_product_attributes_custom_value_index')) {
            Schema::table('product_product_attributes', function (Blueprint $table): void {
                $table->dropIndex('product_product_attributes_custom_value_index');
            });
        }

        Schema::table('product_product_attributes', function (Blueprint $table): void {
            $table->text('custom_value')
                ->nullable()
                ->comment('Ручное/импортированное значение характеристики для товара')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_product_attributes')
            || ! Schema::hasColumn('product_product_attributes', 'custom_value')) {
            return;
        }

        // Возврат к VARCHAR возможен только когда все значения снова укладываются в 255 символов.
        Schema::table('product_product_attributes', function (Blueprint $table): void {
            $table->string('custom_value', 255)
                ->nullable()
                ->comment('Ручное значение характеристики для товара')
                ->change();
            $table->index('custom_value');
        });
    }
};
