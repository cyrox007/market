<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Принудительно добавляет все недостающие колонки в payment_methods
     * Использует прямой SQL для надежности
     */
    public function up(): void
    {
        if (!Schema::hasTable('payment_methods')) {
            // Если таблицы нет, создаем её полностью
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique()->comment('Уникальный код метода оплаты');
                $table->string('name')->comment('Название метода оплаты');
                $table->text('description')->nullable()->comment('Описание метода оплаты');
                $table->string('icon')->nullable()->comment('Иконка метода оплаты');
                $table->boolean('is_enabled')->default(true)->comment('Включен ли метод оплаты');
                $table->boolean('is_active')->default(true)->comment('Активен ли метод оплаты');
                $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
                $table->string('gateway')->nullable()->comment('Платежный шлюз');
                $table->json('configuration')->nullable()->comment('Конфигурация метода оплаты');
                $table->timestamps();
                
                $table->index('code');
                $table->index('is_enabled');
                $table->index('is_active');
                $table->index('sort_order');
            });
            return;
        }

        // Получаем список существующих колонок (работает с MySQL и SQLite)
        $existingColumns = Schema::getColumnListing('payment_methods');

        $columnsToAdd = [
            'code' => fn (Blueprint $table) => $table->string('code')->nullable(),
            'name' => fn (Blueprint $table) => $table->string('name')->nullable(),
            'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
            'icon' => fn (Blueprint $table) => $table->string('icon')->nullable(),
            'is_enabled' => fn (Blueprint $table) => $table->boolean('is_enabled')->default(true),
            'is_active' => fn (Blueprint $table) => $table->boolean('is_active')->default(true),
            'sort_order' => fn (Blueprint $table) => $table->integer('sort_order')->default(0),
            'gateway' => fn (Blueprint $table) => $table->string('gateway')->nullable(),
            'configuration' => fn (Blueprint $table) => $table->json('configuration')->nullable(),
        ];

        foreach ($columnsToAdd as $columnName => $callback) {
            if (!in_array($columnName, $existingColumns)) {
                Schema::table('payment_methods', $callback);
            }
        }

        // Добавляем уникальный индекс для code, если колонка есть (в т.ч. только что добавлена)
        $columnsAfterAdd = Schema::getColumnListing('payment_methods');
        if (in_array('code', $columnsAfterAdd)) {
            try {
                Schema::table('payment_methods', fn (Blueprint $table) => $table->unique('code'));
            } catch (\Exception $e) {
                // Игнорируем, если индекс уже существует
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Не удаляем колонки, так как они могут использоваться
    }
};
