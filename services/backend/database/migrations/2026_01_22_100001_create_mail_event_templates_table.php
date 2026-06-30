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
        Schema::create('mail_event_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_event_id')->constrained('mail_events')->onDelete('cascade');
            $table->string('name')->comment('Название шаблона');
            $table->string('subject')->comment('Тема письма (с поддержкой переменных)');
            $table->text('body')->comment('Тело письма (с поддержкой переменных)');
            $table->json('variables')->nullable()->comment('Доступные переменные для подстановки');
            $table->boolean('is_active')->default(true)->comment('Активен ли шаблон');
            $table->boolean('is_default')->default(false)->comment('Шаблон по умолчанию для события');
            $table->timestamps();

            $table->index(['mail_event_id', 'is_active']);
            $table->index(['mail_event_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_event_templates');
    }
};
