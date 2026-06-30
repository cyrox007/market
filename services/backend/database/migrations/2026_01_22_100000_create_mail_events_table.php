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
        Schema::create('mail_events', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Уникальный код события (например, order.created)');
            $table->string('name')->comment('Название события');
            $table->text('description')->nullable()->comment('Описание события');
            $table->boolean('is_active')->default(true)->comment('Активно ли событие');
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_events');
    }
};
