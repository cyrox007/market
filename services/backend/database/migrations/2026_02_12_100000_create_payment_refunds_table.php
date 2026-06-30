<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('refund_id', 80)->comment('Идентификатор возврата в API банка');
            $table->decimal('amount', 15, 4);
            $table->string('status', 50)->default('pending')->comment('pending, in_progress, completed, failed');
            $table->string('gateway', 50)->comment('raiffeisen_ecom, raiffeisen_acquiring и т.д.');
            $table->json('meta')->nullable()->comment('Ответ API, remote_id и т.д.');
            $table->string('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payment_id', 'refund_id']);
            $table->index('status');
            $table->index('gateway');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
