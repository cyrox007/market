<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Связь локаций доставки с типами обработки доставки (разгрузка, подъем)
     */
    public function up(): void
    {
        if (Schema::hasTable('shipping_location_delivery_handling')) {
            return;
        }

        Schema::create('shipping_location_delivery_handling', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_location_id')->constrained('shipping_locations')->onDelete('cascade');
            // MySQL: имя FK по умолчанию здесь > 64 символов, задаём короткое вручную
            $table->foreignId('delivery_handling_type_id')
                ->constrained('delivery_handling_types', 'id', 'sh_loc_handling_type_fk')
                ->onDelete('cascade');

            // Базовая стоимость обработки (если не зависит от этажа)
            $table->decimal('base_price', 10, 2)->nullable()->comment('Базовая стоимость обработки доставки');

            // Для лифта - фиксированная стоимость для любого этажа
            $table->decimal('elevator_price', 10, 2)->nullable()->comment('Стоимость обработки с лифтом (для любого этажа)');

            // Для ручного подъема - стоимость по этажам (JSON: {"1": 300, "2": 500, ...})
            $table->json('floor_prices')->nullable()->comment('Стоимость обработки по этажам (для ручного подъема)');

            // Дополнительные настройки
            $table->boolean('is_active')->default(true)->comment('Активен ли данный тип обработки для локации');
            $table->text('notes')->nullable()->comment('Примечания (ограничения, особенности и т.д.)');

            $table->timestamps();

            // Короткое имя для уникального индекса (MySQL ограничение 64 символа)
            $table->unique(['shipping_location_id', 'delivery_handling_type_id'], 'shipping_loc_handling_unique');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_location_delivery_handling');
    }
};
