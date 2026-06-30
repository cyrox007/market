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
        // Добавляем поле is_variable в таблицу products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'is_variable')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_variable')->default(false)->after('state')->comment('Является ли товар вариативным');
                // Используем unsignedBigInteger для совместимости с типом id в products
                $table->unsignedBigInteger('parent_product_id')->nullable()->after('is_variable')
                    ->comment('Родительский товар для вариаций');
            });

            // Foreign key убран для совместимости - целостность данных поддерживается на уровне приложения
        }

        // Таблица вариаций продуктов
        // Вариации наследуются от Vanilo Product, поэтому используем ту же таблицу products
        // Но добавляем связь через parent_product_id
        // Для вариаций также нужны связи с характеристиками (цвет, размер)

        // Связь вариаций с характеристиками (для быстрого поиска по цвету/размеру)
        if (!Schema::hasTable('product_variant_attributes')) {
            Schema::create('product_variant_attributes', function (Blueprint $table) {
                $table->id();
                // Используем unsignedBigInteger для совместимости с типом id в products
                $table->unsignedBigInteger('product_id')->comment('ID вариации');
                $table->unsignedBigInteger('attribute_id');
                $table->unsignedBigInteger('attribute_value_id');
                $table->timestamps();

                $table->unique(['product_id', 'attribute_id']);
                $table->index('product_id');
                $table->index(['attribute_id', 'attribute_value_id']);
            });

            // Foreign keys убраны для совместимости - целостность данных поддерживается на уровне приложения
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_attributes');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'parent_product_id')) {
                    $table->dropColumn('parent_product_id');
                }
                if (Schema::hasColumn('products', 'is_variable')) {
                    $table->dropColumn('is_variable');
                }
            });
        }
    }
};

