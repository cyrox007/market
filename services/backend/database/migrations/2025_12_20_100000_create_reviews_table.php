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
        if (Schema::hasTable('reviews')) {
            return; // Таблица уже существует
        }

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            // Используем unsignedBigInteger для совместимости с типом id в products (Vanilo)
            // Foreign key убран для совместимости - целостность данных поддерживается на уровне приложения
            $table->unsignedBigInteger('product_id')->comment('ID товара');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name');
            $table->string('email')->nullable();
            $table->tinyInteger('rating')->unsigned()->comment('Рейтинг от 1 до 5');
            $table->text('comment');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->index('product_id');
            $table->index('user_id');
            $table->index('is_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

