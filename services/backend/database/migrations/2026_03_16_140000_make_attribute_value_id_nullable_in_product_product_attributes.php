<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_product_attributes')) {
            return;
        }

        // Делаем attribute_value_id nullable, чтобы можно было хранить только custom_value.
        // Используем сырой SQL, чтобы не требовать doctrine/dbal.
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `product_product_attributes` MODIFY `attribute_value_id` BIGINT UNSIGNED NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_product_attributes')) {
            return;
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            // Возвращаем NOT NULL, по умолчанию 0 (чтобы не нарушить существующие строки).
            DB::statement('ALTER TABLE `product_product_attributes` MODIFY `attribute_value_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};

