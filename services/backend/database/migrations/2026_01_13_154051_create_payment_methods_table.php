<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Методы оплаты заказов
     * Таблица уже существует (Vanilo Payment), добавляем только недостающие поля если нужно
     */
    public function up(): void
    {
        // Таблица уже существует из Vanilo Payment, просто добавляем недостающие поля если нужно
        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                // Добавляем поле code если его нет (критично для сидера)
                if (!Schema::hasColumn('payment_methods', 'code')) {
                    $table->string('code')->unique()->nullable()->after('id')->comment('Уникальный код метода оплаты');
                }

                // Добавляем поле name если его нет
                if (!Schema::hasColumn('payment_methods', 'name')) {
                    $table->string('name')->nullable()->after('code')->comment('Название метода оплаты');
                }

                // Добавляем поле description если его нет
                if (!Schema::hasColumn('payment_methods', 'description')) {
                    $table->text('description')->nullable()->after('name')->comment('Описание метода оплаты');
                }

                // Добавляем поле icon если его нет
                if (!Schema::hasColumn('payment_methods', 'icon')) {
                    $table->string('icon')->nullable()->comment('Иконка метода оплаты (класс иконки или путь к изображению)')->after('description');
                }

                // Добавляем поле is_enabled если его нет
                if (!Schema::hasColumn('payment_methods', 'is_enabled')) {
                    $table->boolean('is_enabled')->default(true)->after('icon')->comment('Включен ли метод оплаты');
                }

                // Добавляем поле is_active если его нет
                if (!Schema::hasColumn('payment_methods', 'is_active')) {
                    $table->boolean('is_active')->default(true)->comment('Активен ли метод оплаты')->after('is_enabled');
                }

                // Добавляем поле sort_order если его нет (может быть под другим именем)
                if (!Schema::hasColumn('payment_methods', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->comment('Порядок сортировки')->after('is_active');
                }

                // Добавляем поле gateway если его нет
                if (!Schema::hasColumn('payment_methods', 'gateway')) {
                    $table->string('gateway')->nullable()->after('sort_order')->comment('Платежный шлюз');
                }

                // Добавляем поле configuration если его нет
                if (!Schema::hasColumn('payment_methods', 'configuration')) {
                    $table->json('configuration')->nullable()->after('gateway')->comment('Конфигурация метода оплаты');
                }
            });
        } else {
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
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
