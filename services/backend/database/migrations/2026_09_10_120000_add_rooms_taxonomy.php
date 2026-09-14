<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Комнаты — вторая таксономия каталога (вариант 2).
 * Одна миграция на весь коммит: поле фильтра, таблица связи и сама таксономия.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Сохранённый фильтр листа-комнаты: применяется к товарам её продуктовых категорий.
        Schema::table('taxons', function (Blueprint $table) {
            $table->json('filters')->nullable()->after('bottom_content');
        });

        // Связь комнатного листа (taxon в таксономии rooms) с продуктовыми категориями.
        // taxons.id — integer unsigned (Vanilo), поэтому FK объявляем вручную под этот тип.
        Schema::create('room_taxon_category', function (Blueprint $table) {
            $table->id();
            $table->integer('room_taxon_id')->unsigned();
            $table->integer('category_id')->unsigned();
            $table->timestamps();

            $table->foreign('room_taxon_id')->references('id')->on('taxons')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('taxons')->cascadeOnDelete();
            $table->unique(['room_taxon_id', 'category_id']);
        });

        // Обязательные данные: сама таксономия «Комнаты» (нужна на каждом окружении).
        DB::table('taxonomies')->updateOrInsert(
            ['slug' => 'rooms'],
            ['name' => 'Комнаты', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('taxonomies')->where('slug', 'rooms')->delete();
        Schema::dropIfExists('room_taxon_category');
        Schema::table('taxons', function (Blueprint $table) {
            $table->dropColumn('filters');
        });
    }
};
