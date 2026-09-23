<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * MySQL не откатывает DDL целиком при ошибке внутри Schema::create().
         * Если предыдущая попытка этой ещё не зарегистрированной миграции оборвалась,
         * могли остаться частично созданные новые таблицы. Они не содержат рабочих
         * данных, поэтому перед повторным созданием очищаем только их.
         */
        Schema::dropIfExists('warehouse_delivery_method_locations');
        Schema::dropIfExists('warehouse_delivery_methods');

        Schema::create('warehouse_delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();
            $table->foreignId('shipping_method_id')
                ->constrained('shipping_methods')
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->unique(
                ['warehouse_id', 'shipping_method_id'],
                'wdm_warehouse_method_unique'
            );
            $table->index(
                ['warehouse_id', 'is_active'],
                'wdm_warehouse_active_idx'
            );
        });

        Schema::create('warehouse_delivery_method_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_delivery_method_id');
            $table->unsignedBigInteger('shipping_location_id');
            $table->decimal('delivery_price', 12, 2)->nullable();
            $table->decimal('free_delivery_threshold', 12, 2)->nullable();
            $table->unsignedSmallInteger('delivery_days_min')->nullable();
            $table->unsignedSmallInteger('delivery_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            /*
             * Явно задаём короткие имена ограничений:
             * автоматически сгенерированное MySQL-имя для длинного названия
             * таблицы превышает лимит идентификатора в 64 символа.
             */
            $table->foreign(
                'warehouse_delivery_method_id',
                'wdml_method_fk'
            )
                ->references('id')
                ->on('warehouse_delivery_methods')
                ->cascadeOnDelete();

            $table->foreign(
                'shipping_location_id',
                'wdml_location_fk'
            )
                ->references('id')
                ->on('shipping_locations')
                ->cascadeOnDelete();

            $table->unique(
                ['warehouse_delivery_method_id', 'shipping_location_id'],
                'wdml_method_location_unique'
            );
            $table->index(
                ['shipping_location_id', 'is_active'],
                'wdml_location_active_idx'
            );
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'warehouse_delivery_method_id')) {
                return;
            }

            $table->foreignId('warehouse_delivery_method_id')
                ->nullable()
                ->after('delivery_warehouse_id')
                ->constrained('warehouse_delivery_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'warehouse_delivery_method_id')) {
                return;
            }

            $table->dropConstrainedForeignId('warehouse_delivery_method_id');
        });

        Schema::dropIfExists('warehouse_delivery_method_locations');
        Schema::dropIfExists('warehouse_delivery_methods');
    }
};
