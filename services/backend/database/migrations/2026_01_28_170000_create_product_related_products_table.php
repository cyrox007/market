<?php

use Filament\Facades\Filament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Если таблица уже есть (создана ранее), ничего не делаем,
        // чтобы миграция могла пометиться выполненной без ошибки.
        if (!Schema::hasTable('product_related_products')) {
            Schema::create('product_related_products', function (Blueprint $table) {
                $table->id();
                // Используем unsignedBigInteger, как и в product_variant_attributes,
                // без foreign key – целостность данных контролируется приложением.
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('related_product_id');
                $table->timestamps();

                // Запрещаем дублирование одной и той же связи в одном направлении
                $table->unique(['product_id', 'related_product_id'], 'product_related_unique');
                $table->index('related_product_id', 'product_related_related_product_id_index');
            });
        }

        // Сбрасываем кэш компонентов Filament, чтобы RelationManager «Сопутствующие»
        // зарегистрировался в Livewire (кэш мог быть собран до добавления класса).
        try {
            $panel = Filament::getPanel('admin_sv');
            if (method_exists($panel, 'clearCachedComponents')) {
                $panel->clearCachedComponents();
            }
        } catch (\Throwable $e) {
            // Игнорируем, если панель ещё не зарегистрирована или кэша нет
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_related_products');
    }
};

