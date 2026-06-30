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
        // Добавляем поля цвета для товаров (для невариативных товаров)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'color')) {
                    $table->string('color')->nullable()->after('parent_product_id')
                        ->comment('Название цвета товара (для невариативных товаров)');
                }
                if (!Schema::hasColumn('products', 'color_code')) {
                    $table->string('color_code', 7)->nullable()->after('color')
                        ->comment('HEX код цвета (например: #808080)');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'color_code')) {
                    $table->dropColumn('color_code');
                }
                if (Schema::hasColumn('products', 'color')) {
                    $table->dropColumn('color');
                }
            });
        }
    }
};

