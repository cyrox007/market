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
        if (Schema::hasTable('additional_services')) {
            return;
        }

        Schema::create('additional_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Название услуги');
            $table->string('slug')->unique()->comment('URL-слаг');
            $table->string('code')->unique()->comment('Уникальный код услуги');
            $table->text('description')->nullable()->comment('Описание услуги');
            $table->string('icon')->nullable()->comment('Иконка (RemixIcon класс, например: ri-tools-line)');
            
            // Тип цены: fixed - фиксированная, from - "от X", custom - цена отдельно (указывается менеджером)
            $table->enum('price_type', ['fixed', 'from', 'custom'])->default('fixed')->comment('Тип цены');
            $table->decimal('base_price', 10, 2)->nullable()->comment('Базовая цена (для fixed и from)');
            
            $table->boolean('is_active')->default(true)->comment('Активна ли услуга');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
            $table->index('slug');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_services');
    }
};
