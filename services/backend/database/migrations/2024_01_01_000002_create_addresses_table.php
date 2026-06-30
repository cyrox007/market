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
        // Используем user_addresses вместо addresses, чтобы избежать конфликта с Vanilo/konekt
        if (Schema::hasTable('user_addresses')) {
            return; // Таблица уже существует
        }

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->comment('Название адреса: Дом, Работа и т.д.');
            $table->string('city');
            $table->string('street');
            $table->string('house');
            $table->string('apartment')->nullable();
            $table->string('entrance')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};