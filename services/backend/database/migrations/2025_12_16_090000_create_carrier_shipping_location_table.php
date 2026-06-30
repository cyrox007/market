<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Связь many-to-many между carriers (Vanilo) и shipping_locations
     * Позволяет назначить несколько служб доставки на одну локацию
     */
    public function up(): void
    {
        Schema::create('carrier_shipping_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained('carriers')->onDelete('cascade');
            $table->foreignId('shipping_location_id')->constrained('shipping_locations')->onDelete('cascade');

            // Дополнительные настройки для конкретной пары carrier-location
            $table->decimal('base_price', 10, 2)->nullable()->comment('Базовая цена доставки для этого carrier в этой локации');
            $table->decimal('free_delivery_threshold', 10, 2)->nullable()->comment('Порог бесплатной доставки');
            $table->integer('delivery_days_min')->nullable()->comment('Минимальный срок доставки');
            $table->integer('delivery_days_max')->nullable()->comment('Максимальный срок доставки');
            $table->boolean('is_active')->default(true)->comment('Активна ли эта связь');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();

            // Индексы
            $table->index('carrier_id');
            $table->index('shipping_location_id');
            $table->index('is_active');
            $table->index('sort_order');

            // Уникальность пары carrier-location
            $table->unique(['carrier_id', 'shipping_location_id'], 'carrier_location_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrier_shipping_location');
    }
};
