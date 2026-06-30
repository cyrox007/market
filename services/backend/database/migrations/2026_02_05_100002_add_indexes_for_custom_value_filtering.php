<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Составной индекс для фильтрации по attribute_id + custom_value.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_variant_attributes')) {
            try {
                Schema::table('product_variant_attributes', function (Blueprint $table) {
                    $table->index(['attribute_id', 'custom_value'], 'pva_attr_custom_idx');
                });
            } catch (\Exception $e) {
                // Индекс уже существует
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_variant_attributes')) {
            try {
                Schema::table('product_variant_attributes', function (Blueprint $table) {
                    $table->dropIndex('pva_attr_custom_idx');
                });
            } catch (\Exception $e) {
                // Индекс не найден
            }
        }
    }
};
