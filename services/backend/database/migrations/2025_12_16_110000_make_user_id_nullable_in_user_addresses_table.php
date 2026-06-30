<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Делает user_id nullable для поддержки адресов гостевых заказов
     */
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            // Удаляем внешний ключ
            $table->dropForeign(['user_id']);

            // Делаем колонку nullable
            $table->foreignId('user_id')->nullable()->change();

            // Восстанавливаем внешний ключ с onDelete('set null')
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            // Удаляем внешний ключ
            $table->dropForeign(['user_id']);

            // Удаляем адреса без пользователя перед изменением колонки
            \App\Models\Address\Address::whereNull('user_id')->delete();

            // Делаем колонку NOT NULL
            $table->foreignId('user_id')->nullable(false)->change();

            // Восстанавливаем внешний ключ с onDelete('cascade')
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
