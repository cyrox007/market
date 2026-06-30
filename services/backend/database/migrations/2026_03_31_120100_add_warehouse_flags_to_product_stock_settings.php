<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_settings', function (Blueprint $table) {
            $table->boolean('warehouse_accounting_enabled')
                ->default(false)
                ->after('show_exact_above');
            $table->boolean('fallback_to_first_warehouse')
                ->default(true)
                ->after('warehouse_accounting_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_settings', function (Blueprint $table) {
            $table->dropColumn(['warehouse_accounting_enabled', 'fallback_to_first_warehouse']);
        });
    }
};
