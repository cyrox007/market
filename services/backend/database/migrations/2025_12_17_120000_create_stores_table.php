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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->text('address');
            $table->string('city');
            $table->string('phone');
            $table->string('hours')->nullable();
            $table->string('coordinates')->nullable()->comment('Latitude,Longitude для карты');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('yandex_map')->nullable()->comment('Код встраивания или ссылка на Яндекс карту');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            // Составной индекс для быстрой фильтрации активных записей с сортировкой по приоритету
            // Используется в запросах: Store::active()->ordered()
            // Аналогично используется в таблицах sliders и articles
            $table->index(['is_active', 'priority']);

            // Индекс для фильтрации по городу (используется в API: Store::byCity($city))
            // Ускоряет запросы при фильтрации магазинов по городу
            $table->index('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
