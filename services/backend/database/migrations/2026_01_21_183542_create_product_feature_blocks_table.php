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
        Schema::create('product_feature_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('Заголовок блока (например, "Доставка от 1 дня")');
            $table->string('subtitle')->nullable()->comment('Подзаголовок (например, "Бесплатно от 30 000 ₽")');
            $table->string('icon')->nullable()->comment('Класс иконки RemixIcon (например, "ri-truck-line"). Опционально, если загружено изображение.');
            $table->string('icon_color')->default('gray-600')->comment('Цвет иконки (например, "red-600")');
            $table->string('bg_color')->default('gray-100')->comment('Цвет фона иконки (например, "red-100")');
            $table->boolean('is_active')->default(true)->comment('Активность блока');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_feature_blocks');
    }
};
