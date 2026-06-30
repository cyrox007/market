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
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'shipping_location_id')) {
                $table->foreignId('shipping_location_id')
                    ->nullable()
                    ->after('city')
                    ->constrained('shipping_locations')
                    ->onDelete('set null')
                    ->comment('Регион магазина (ShippingLocation типа region)');
                
                $table->index('shipping_location_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'shipping_location_id')) {
                $table->dropForeign(['shipping_location_id']);
                $table->dropIndex(['shipping_location_id']);
                $table->dropColumn('shipping_location_id');
            }
        });
    }
};
