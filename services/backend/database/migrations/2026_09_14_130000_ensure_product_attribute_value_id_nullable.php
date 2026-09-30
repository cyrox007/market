<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('product_product_attributes')
            || ! Schema::hasColumn('product_product_attributes', 'attribute_value_id')) {
            return;
        }

        // Историческая миграция делала поле nullable только на MySQL.
        // Этот migration выравнивает схему SQLite/других БД и безопасно повторяет
        // нужную декларацию на MySQL, где custom_value уже поддерживался фактически.
        Schema::table('product_product_attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('attribute_value_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_product_attributes')
            || ! Schema::hasColumn('product_product_attributes', 'attribute_value_id')) {
            return;
        }

        // Строки только с custom_value нельзя представить в старой NOT NULL схеме.
        DB::table('product_product_attributes')->whereNull('attribute_value_id')->delete();

        Schema::table('product_product_attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('attribute_value_id')->nullable(false)->change();
        });
    }
};
