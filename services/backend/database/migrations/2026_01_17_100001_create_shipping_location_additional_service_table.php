<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('shipping_location_additional_service')) {
            return;
        }

        Schema::create('shipping_location_additional_service', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_location_id');
            $table->unsignedBigInteger('additional_service_id');
            
            // Переопределение цены для конкретного региона (если null, используется base_price из услуги)
            $table->decimal('price', 10, 2)->nullable()->comment('Цена услуги для данного региона');
            
            $table->boolean('is_active')->default(true)->comment('Активна ли услуга для данного региона');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();

            $table->foreign('shipping_location_id', 'loc_add_svc_loc_fk')
                ->references('id')
                ->on('shipping_locations')
                ->onDelete('cascade');
            
            $table->foreign('additional_service_id', 'loc_add_svc_svc_fk')
                ->references('id')
                ->on('additional_services')
                ->onDelete('cascade');

            $table->unique(['shipping_location_id', 'additional_service_id'], 'location_service_unique');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_location_additional_service');
    }
};
