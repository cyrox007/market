<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_delivery_profiles', fn (Blueprint $table) => $table->json('directory_territories')->nullable());
        Schema::table('orders', fn (Blueprint $table) => $table->json('fulfillment_snapshot')->nullable());
    }

    public function down(): void
    {
        Schema::table('warehouse_delivery_profiles', fn (Blueprint $table) => $table->dropColumn('directory_territories'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('fulfillment_snapshot'));
    }
};
