<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_additional_services')) {
            return;
        }

        Schema::create('order_additional_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('additional_service_id');
            
            // Сохраняем название и цену на момент заказа
            $table->string('service_name')->comment('Название услуги на момент заказа');
            $table->decimal('price', 10, 2)->comment('Цена услуги на момент заказа');
            $table->string('price_type')->comment('Тип цены на момент заказа');
            $table->string('icon')->nullable()->comment('Иконка услуги');
            
            $table->timestamps();

            $table->index('order_id');
            $table->index('additional_service_id');
        });

        // Создаем foreign keys с обработкой ошибок (на случай если таблицы созданы Vanilo)
        try {
            DB::statement('ALTER TABLE order_additional_services ADD CONSTRAINT order_additional_services_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE');
        } catch (\Exception $e) {
            Log::warning('Could not create foreign key constraint for order_additional_services.order_id: ' . $e->getMessage());
        }

        try {
            DB::statement('ALTER TABLE order_additional_services ADD CONSTRAINT order_additional_services_additional_service_id_foreign FOREIGN KEY (additional_service_id) REFERENCES additional_services(id) ON DELETE CASCADE');
        } catch (\Exception $e) {
            Log::warning('Could not create foreign key constraint for order_additional_services.additional_service_id: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем foreign keys перед удалением таблицы
        try {
            DB::statement('ALTER TABLE order_additional_services DROP FOREIGN KEY IF EXISTS order_additional_services_order_id_foreign');
        } catch (\Exception $e) {
            // Игнорируем ошибки при удалении
        }

        try {
            DB::statement('ALTER TABLE order_additional_services DROP FOREIGN KEY IF EXISTS order_additional_services_additional_service_id_foreign');
        } catch (\Exception $e) {
            // Игнорируем ошибки при удалении
        }

        Schema::dropIfExists('order_additional_services');
    }
};
