<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            // На главной два независимых блока: верхний promo board и нижний banner.
            // Все старые слайды считаем верхними, чтобы не ломать существующие данные.
            $table->string('placement', 20)
                ->default('top')
                ->after('id');

            // Верхний блок на desktop состоит из большой карусели и двух боковых карточек.
            // На mobile боковые карточки фронт не показывает.
            $table->string('slot', 20)
                ->default('main')
                ->after('placement');

            $table->string('badge_tone', 20)
                ->nullable()
                ->after('badge_icon');

            // Fallback URL нужен в том числе для демо-данных. Загруженный MediaLibrary-файл
            // всегда имеет приоритет над этими URL.
            $table->string('image_url', 2048)
                ->nullable()
                ->after('badge_tone');
            $table->string('mobile_image_url', 2048)
                ->nullable()
                ->after('image_url');

            $table->index(
                ['placement', 'slot', 'is_active', 'priority'],
                'sliders_placement_slot_active_priority_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            $table->dropIndex('sliders_placement_slot_active_priority_index');
            $table->dropColumn([
                'placement',
                'slot',
                'badge_tone',
                'image_url',
                'mobile_image_url',
            ]);
        });
    }
};
