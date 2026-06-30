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
        if (Schema::hasTable('product_variant_attributes') && Schema::hasColumn('product_variant_attributes', 'custom_value')) {
            Schema::table('product_variant_attributes', function (Blueprint $table) {
                // Изменяем тип с string(500) на text для хранения больших данных
                $table->text('custom_value')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_variant_attributes') && Schema::hasColumn('product_variant_attributes', 'custom_value')) {
            Schema::table('product_variant_attributes', function (Blueprint $table) {
                $table->string('custom_value', 500)->nullable()->change();
            });
        }
    }
};
