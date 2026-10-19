<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_transfer_runs', function (Blueprint $table) {
            $table->boolean('apply_enabled')->default(false);
        });
        Schema::table('catalog_transfer_rows', function (Blueprint $table) {
            $table->unsignedBigInteger('applied_product_id')->nullable();
            $table->boolean('product_created')->default(false);
            $table->timestamp('table_applied_at')->nullable();
            $table->timestamp('api_applied_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_transfer_rows', fn (Blueprint $table) => $table->dropColumn(['applied_product_id', 'product_created', 'table_applied_at', 'api_applied_at']));
        Schema::table('catalog_transfer_runs', fn (Blueprint $table) => $table->dropColumn('apply_enabled'));
    }
};
