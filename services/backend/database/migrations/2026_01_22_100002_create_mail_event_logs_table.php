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
        Schema::create('mail_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_event_id')->constrained('mail_events')->onDelete('cascade');
            $table->foreignId('mail_event_template_id')->nullable()->constrained('mail_event_templates')->onDelete('set null');
            $table->string('recipient_email')->comment('Email получателя');
            $table->string('subject')->comment('Тема письма');
            $table->text('body')->comment('Тело письма');
            $table->string('status')->default('pending')->comment('Статус: pending, sent, failed');
            $table->text('error_message')->nullable()->comment('Сообщение об ошибке, если отправка не удалась');
            $table->timestamp('sent_at')->nullable()->comment('Время отправки');
            $table->json('variables')->nullable()->comment('Переменные, использованные при отправке');
            $table->timestamps();

            $table->index(['mail_event_id', 'status']);
            $table->index('recipient_email');
            $table->index('sent_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_event_logs');
    }
};
