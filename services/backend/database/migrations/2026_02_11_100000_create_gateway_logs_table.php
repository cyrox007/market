<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Единый лог взаимодействий со шлюзами (оплата, доставка и т.д.).
     * Позволяет видеть кто/когда/что оплатил и аудит по каждому шлюзу.
     */
    public function up(): void
    {
        Schema::create('gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 64)->index()->comment('Идентификатор шлюза: raiffeisen_acquiring, и т.д.');
            $table->string('channel', 32)->default('payment')->index()->comment('Канал: payment, delivery, ...');
            $table->string('action', 64)->index()->comment('Действие: created, callback_received, callback_success, callback_failed');
            $table->unsignedBigInteger('order_id')->nullable()->index()->comment('Заказ (для быстрой связи и фильтрации)');
            $table->string('loggable_type')->nullable()->index();
            $table->unsignedBigInteger('loggable_id')->nullable()->index();
            $table->string('message', 500)->nullable();
            $table->string('level', 16)->default('info')->index()->comment('info, warning, error');
            $table->json('meta')->nullable()->comment('amount, order_id, order_number, remote_id, ip, contact_email, и т.д.');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['gateway', 'channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_logs');
    }
};
