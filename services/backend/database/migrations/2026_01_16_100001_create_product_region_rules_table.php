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
        if (Schema::hasTable('product_region_rules')) {
            return;
        }

        Schema::create('product_region_rules', function (Blueprint $table) {
            $table->id();
            
            // Связи
            // Используем unsignedBigInteger для совместимости с типом id в products (Vanilo)
            $table->unsignedBigInteger('product_id')
                ->nullable()
                ->comment('ID товара (nullable, если null - правило для всех товаров)');
            
            $table->unsignedBigInteger('variant_id')
                ->nullable()
                ->comment('ID вариации (nullable, если null - правило для всех вариаций товара)');
            
            $table->foreignId('shipping_location_id')
                ->constrained('shipping_locations')
                ->onDelete('cascade')
                ->comment('ID региона (обязательно)');
            
            // Foreign keys убраны для product_id и variant_id для совместимости с Vanilo
            // Целостность данных поддерживается на уровне приложения
            
            // Правила цены
            $table->decimal('price_override', 10, 2)
                ->nullable()
                ->comment('Переопределение цены (nullable, decimal)');
            
            $table->enum('price_modifier_type', ['fixed', 'percent', 'multiply'])
                ->nullable()
                ->comment('Тип модификатора: fixed (фиксированная сумма), percent (процент), multiply (множитель)');
            
            $table->decimal('price_modifier_value', 10, 2)
                ->nullable()
                ->comment('Значение модификатора (nullable, decimal)');
            
            // Видимость и доставка
            $table->boolean('is_hidden')
                ->default(false)
                ->comment('Скрыть товар в локации доставки (boolean, default false - показывать, правило применяется к локации и всем её дочерним локациям)');
            
            $table->integer('delivery_days_override')
                ->nullable()
                ->comment('Переопределение срока доставки (nullable, integer)');
            
            // Управление
            $table->boolean('is_active')
                ->default(true)
                ->comment('Активность правила (boolean, default true)');
            
            $table->integer('priority')
                ->default(0)
                ->comment('Приоритет правила (integer, default 0, для разрешения конфликтов)');
            
            $table->timestamps();
            
            // Индексы
            $table->index(['product_id', 'shipping_location_id']);
            $table->index(['variant_id', 'shipping_location_id']);
            $table->index(['shipping_location_id', 'is_active']);
            $table->index('priority');
            
            // Уникальность: не может быть двух активных правил для одной комбинации
            // Но разрешаем несколько правил с разными приоритетами
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_region_rules');
    }
};
