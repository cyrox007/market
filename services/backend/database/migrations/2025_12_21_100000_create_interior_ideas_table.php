<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interior_ideas', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Сначала удаляем таблицу hotspots, если она существует (чтобы не было проблем с foreign keys)
        if (Schema::hasTable('interior_idea_hotspots')) {
            // Удаляем foreign keys через SQL перед удалением таблицы
            try {
                DB::statement('ALTER TABLE interior_idea_hotspots DROP FOREIGN KEY IF EXISTS interior_idea_hotspots_interior_idea_id_foreign');
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
            try {
                DB::statement('ALTER TABLE interior_idea_hotspots DROP FOREIGN KEY IF EXISTS interior_idea_hotspots_product_id_foreign');
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
            Schema::dropIfExists('interior_idea_hotspots');
        }
        
        Schema::dropIfExists('interior_ideas');
    }
};
