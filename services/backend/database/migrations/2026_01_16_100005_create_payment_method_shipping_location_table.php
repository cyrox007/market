<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Связь many-to-many между payment_methods и shipping_locations
     * Позволяет назначить несколько методов оплаты на одну локацию
     */
    public function up(): void
    {
        if (Schema::hasTable('payment_method_shipping_location')) {
            return;
        }

        Schema::create('payment_method_shipping_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->onDelete('cascade');
            $table->foreignId('shipping_location_id')->constrained('shipping_locations')->onDelete('cascade');

            // Дополнительные настройки для конкретной пары payment_method-location
            $table->boolean('is_active')->default(true)->comment('Активна ли эта связь');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();

            // Индексы
            $table->index('payment_method_id');
            $table->index('shipping_location_id');
            $table->index('is_active');
            $table->index('sort_order');

            // Уникальность пары payment_method-location
            $table->unique(['payment_method_id', 'shipping_location_id'], 'payment_location_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_method_shipping_location');
    }
};
