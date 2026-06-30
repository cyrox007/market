<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Единая модель для иерархии адресов доставки
     * Использует self-referencing для построения иерархии: федеральный округ -> регион -> город
     */
    public function up(): void
    {
        Schema::create('shipping_locations', function (Blueprint $table) {
            $table->id();

            // Иерархия (self-referencing)
            $table->foreignId('parent_id')->nullable()->constrained('shipping_locations')->onDelete('cascade');

            // Основная информация
            $table->string('name')->comment('Название локации');
            $table->string('slug')->comment('URL-слаг');
            $table->string('code', 10)->nullable()->comment('Код локации (например, код субъекта РФ)');
            $table->enum('type', ['federal_district', 'region', 'locality'])->comment('Тип локации в иерархии');
            $table->enum('location_type', ['federal_district', 'republic', 'oblast', 'krai', 'autonomous_okrug', 'federal_city', 'city', 'town', 'village', 'urban_settlement', 'district'])->nullable()->comment('Тип географической единицы');
            $table->string('postal_code', 10)->nullable()->comment('Почтовый индекс');

            // Стоимость доставки (наследуется от родителя, если не указана)
            $table->decimal('delivery_price', 10, 2)->nullable()->comment('Стоимость доставки');
            $table->decimal('free_delivery_threshold', 10, 2)->nullable()->comment('Минимальная сумма заказа для бесплатной доставки');

            // Сроки доставки
            $table->integer('delivery_days_min')->nullable()->comment('Минимальный срок доставки в днях');
            $table->integer('delivery_days_max')->nullable()->comment('Максимальный срок доставки в днях');

            // Интеграция с Vanilo Shipping
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->onDelete('set null');

            // Сборка мебели
            $table->boolean('requires_assembly')->default(false)->comment('Требуется ли сборка мебели');
            $table->decimal('assembly_price', 10, 2)->nullable()->comment('Стоимость сборки');
            $table->integer('assembly_days')->nullable()->comment('Срок сборки в днях');

            // Ограничения доставки
            $table->decimal('min_order_amount', 10, 2)->nullable()->comment('Минимальная сумма заказа для доставки');
            $table->decimal('max_order_weight', 10, 2)->nullable()->comment('Максимальный вес заказа в кг');
            $table->decimal('max_order_volume', 10, 2)->nullable()->comment('Максимальный объем заказа в м³');

            // Статус и сортировка
            $table->boolean('is_active')->default(true)->comment('Активна ли локация для доставки');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();

            // Индексы
            $table->index('parent_id');
            $table->index('type');
            $table->index('slug');
            $table->index('code');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index('shipping_method_id');

            // Уникальность slug в рамках одного родителя
            $table->unique(['parent_id', 'slug'], 'shipping_location_parent_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_locations');
    }
};
