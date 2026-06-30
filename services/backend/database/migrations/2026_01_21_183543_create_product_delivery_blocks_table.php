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
        Schema::create('product_delivery_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('Заголовок блока (например, "Доставка по городу")');
            $table->text('description')->nullable()->comment('Описание блока');
            $table->string('icon')->nullable()->comment('Класс иконки RemixIcon. Опционально, если загружено изображение.');
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
        Schema::dropIfExists('product_delivery_blocks');
    }
};
