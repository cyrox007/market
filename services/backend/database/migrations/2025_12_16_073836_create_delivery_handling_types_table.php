<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Типы обработки доставки (разгрузка, подъем и т.д.)
     * По PSR стандартам: DeliveryHandlingType
     */
    public function up(): void
    {
        Schema::create('delivery_handling_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Название типа обработки доставки');
            $table->string('slug')->unique()->comment('URL-слаг');
            $table->string('code')->unique()->comment('Код типа (elevator, manual_lift, etc.)');
            $table->text('description')->nullable()->comment('Описание типа обработки');

            // Параметры для расчета стоимости
            $table->boolean('requires_floor')->default(false)->comment('Требуется ли указание этажа');
            $table->integer('max_floor')->nullable()->comment('Максимальный этаж для данного типа');
            $table->boolean('requires_elevator')->default(false)->comment('Требуется ли наличие лифта');

            // Статус и сортировка
            $table->boolean('is_active')->default(true)->comment('Активен ли тип обработки');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();

            $table->index('sort_order');
            $table->index('is_active');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_handling_types');
    }
};
