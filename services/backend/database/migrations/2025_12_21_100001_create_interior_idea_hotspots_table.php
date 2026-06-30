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
        Schema::create('interior_idea_hotspots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('interior_idea_id')->comment('ID идеи для интерьера');
            $table->unsignedBigInteger('product_id')->comment('ID товара');
            $table->decimal('x', 5, 2)->comment('Координата X в процентах (0-100)');
            $table->decimal('y', 5, 2)->comment('Координата Y в процентах (0-100)');
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['interior_idea_id', 'priority']);
            $table->index('product_id');
            // Foreign keys убраны для совместимости - целостность данных поддерживается на уровне приложения
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем foreign keys через SQL, если они существуют
        if (Schema::hasTable('interior_idea_hotspots')) {
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
        }
        
        Schema::dropIfExists('interior_idea_hotspots');
    }
};
