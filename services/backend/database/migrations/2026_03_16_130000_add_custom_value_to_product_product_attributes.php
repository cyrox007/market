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
        if (Schema::hasTable('product_product_attributes')) {
            Schema::table('product_product_attributes', function (Blueprint $table) {
                if (!Schema::hasColumn('product_product_attributes', 'custom_value')) {
                    $table->string('custom_value')->nullable()->after('attribute_value_id')
                        ->comment('Ручное значение характеристики для товара');
                    $table->index('custom_value');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_product_attributes')) {
            Schema::table('product_product_attributes', function (Blueprint $table) {
                if (Schema::hasColumn('product_product_attributes', 'custom_value')) {
                    $table->dropIndex(['custom_value']);
                    $table->dropColumn('custom_value');
                }
            });
        }
    }
};

