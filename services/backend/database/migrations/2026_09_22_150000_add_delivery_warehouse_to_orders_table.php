<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_warehouse_id')) {
                $table->foreignId('delivery_warehouse_id')
                    ->nullable()
                    ->after('shipping_location_id')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_warehouse_id')) {
                $table->dropConstrainedForeignId('delivery_warehouse_id');
            }
        });
    }
};
