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
        // Таблица характеристик (цвет, размер, материал и т.д.)
        if (!Schema::hasTable('product_attributes')) {
            Schema::create('product_attributes', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Название характеристики (Цвет, Размер, Материал)');
                $table->string('slug')->unique()->comment('Уникальный идентификатор');
                $table->string('type')->default('text')->comment('Тип: text, color, select, number');
                $table->boolean('is_filterable')->default(true)->comment('Использовать в фильтрах');
                $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
                $table->timestamps();

                $table->index('slug');
                $table->index('is_filterable');
            });
        }

        // Таблица значений характеристик
        if (!Schema::hasTable('product_attribute_values')) {
            Schema::create('product_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
                $table->string('value')->comment('Значение (Серый, Большой, Дерево)');
                $table->string('slug')->comment('Уникальный идентификатор значения');
                $table->string('color_code')->nullable()->comment('Код цвета для цветовых характеристик (#808080)');
                $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
                $table->timestamps();

                $table->unique(['attribute_id', 'slug']);
                $table->index('attribute_id');
            });
        }

        // Связь продуктов с характеристиками
        if (!Schema::hasTable('product_product_attributes')) {
            Schema::create('product_product_attributes', function (Blueprint $table) {
                $table->id();
                // Используем unsignedBigInteger для совместимости с типом id в products
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('attribute_id');
                $table->unsignedBigInteger('attribute_value_id');
                $table->timestamps();

                $table->unique(['product_id', 'attribute_id', 'attribute_value_id'], 'prod_attr_value_unique');
                $table->index('product_id');
                $table->index('attribute_id');
                $table->index('attribute_value_id');
            });
            // Foreign keys убраны для совместимости - целостность данных поддерживается на уровне приложения
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_product_attributes');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
    }
};

