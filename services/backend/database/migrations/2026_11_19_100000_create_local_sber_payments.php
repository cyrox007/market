<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_sber_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('order_number');
            $table->unsignedBigInteger('amount');
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('refund_id')->nullable();
            $table->boolean('unreachable')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_sber_payments');
    }
};
