<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_shipping_location', function (Blueprint $table) {
            $table->decimal('delivery_price', 12, 2)->nullable()->after('shipping_location_id');
            $table->unsignedSmallInteger('delivery_days_min')->nullable()->after('delivery_price');
            $table->unsignedSmallInteger('delivery_days_max')->nullable()->after('delivery_days_min');
            $table->boolean('is_active')->default(true)->after('delivery_days_max');
            $table->integer('priority')->default(0)->after('is_active');

            $table->index(['shipping_location_id', 'is_active'], 'warehouse_location_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_shipping_location', function (Blueprint $table) {
            $table->dropIndex('warehouse_location_active_idx');
            $table->dropColumn([
                'delivery_price',
                'delivery_days_min',
                'delivery_days_max',
                'is_active',
                'priority',
            ]);
        });
    }
};
