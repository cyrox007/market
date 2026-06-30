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
        // Таблица подборок товаров
        if (!Schema::hasTable('product_collections')) {
            Schema::create('product_collections', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Название подборки (например, "Популярное", "Новинки", "Акции")');
                $table->string('slug')->unique()->comment('Уникальный идентификатор для API (featured, new, sale)');
                $table->enum('scope_type', ['featured', 'new', 'sale'])->nullable()->comment('Тип скоупа для автоматической фильтрации');
                $table->boolean('is_auto')->default(false)->comment('Автоматическая подборка по скоупу или ручная');
                $table->boolean('is_active')->default(true)->comment('Активна ли подборка');
                $table->integer('priority')->default(0)->comment('Порядок сортировки');
                $table->integer('limit')->default(12)->comment('Максимальное количество товаров');
                $table->timestamps();

                $table->index('slug');
                $table->index('scope_type');
                $table->index('is_active');
                $table->index('priority');
            });
        }

        // Связь many-to-many между товарами и подборками
        if (!Schema::hasTable('product_product_collection')) {
            Schema::create('product_product_collection', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->comment('ID товара');
                $table->unsignedBigInteger('product_collection_id')->comment('ID подборки');
                $table->integer('sort_order')->default(0)->comment('Порядок сортировки товара в подборке');
                $table->timestamps();

                $table->unique(['product_id', 'product_collection_id'], 'product_collection_unique');
                $table->index('product_id');
                $table->index('product_collection_id');
                $table->index('sort_order');
            });
            // Foreign keys убраны для совместимости - целостность данных поддерживается на уровне приложения
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_product_collection');
        Schema::dropIfExists('product_collections');
    }
};
