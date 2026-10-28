<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_methods', 'is_coming_soon')) {
            Schema::table('payment_methods', fn (Blueprint $table) => $table->boolean('is_coming_soon')->default(false));
        }
        // Do not overwrite an existing operator-configured payment method.
        DB::table('payment_methods')->insertOrIgnore([
            'code' => 'digital_ruble', 'name' => 'Цифровой рубль', 'description' => 'Скоро',
            'is_active' => false, 'is_enabled' => false, 'is_coming_soon' => true,
            'gateway' => 'manual', 'configuration' => '{}', 'sort_order' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('payment_methods', fn (Blueprint $table) => $table->dropColumn('is_coming_soon'));
    }
};
