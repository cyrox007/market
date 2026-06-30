<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_stock_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('stock_low_max')->default(1)->comment('Максимум для категории "мало" (< stock_low_max)');
            $table->integer('stock_medium_max')->default(5)->comment('Максимум для категории "средне" (>= stock_low_max && <= stock_medium_max)');
            $table->integer('stock_high_max')->default(10)->comment('Максимум для категории "много" (> stock_medium_max && <= stock_high_max)');
            $table->integer('show_exact_above')->default(10)->comment('Показывать точное число если остаток больше этого значения (0 = всегда категория)');
            $table->timestamps();
        });

        // Создаем singleton запись
        DB::table('product_stock_settings')->insert([
            'id' => 1,
            'stock_low_max' => 1,
            'stock_medium_max' => 5,
            'stock_high_max' => 10,
            'show_exact_above' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stock_settings');
    }
};
