<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Добавляет связь между адресами пользователей и локациями доставки
     * Это позволяет использовать нашу иерархию адресов вместе с Vanilo
     */
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('user_addresses', 'shipping_location_id')) {
                $table->foreignId('shipping_location_id')->nullable()->constrained('shipping_locations')->onDelete('set null');
                $table->index('shipping_location_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropForeign(['shipping_location_id']);
            $table->dropColumn('shipping_location_id');
        });
    }
};
