<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = Schema::hasTable('local_sber_payments');
        if (! $exists) {
            Schema::create('local_sber_payments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                // Vanilo orders.id is an unsigned INT, not Laravel's default BIGINT.
                $table->unsignedInteger('order_id');
                $table->string('order_number');
                $table->unsignedBigInteger('amount');
                $table->unsignedTinyInteger('status')->default(0);
                $table->unsignedBigInteger('refunded_amount')->default(0);
                $table->string('refund_id')->nullable();
                $table->boolean('unreachable')->default(false);
                $table->timestamps();
            });
        }
        // MySQL retains CREATE TABLE if the subsequent ADD FOREIGN KEY fails.
        // Repair that incomplete first attempt without dropping the table or its rows.
        $hasForeign = collect(Schema::getForeignKeys('local_sber_payments'))
            ->contains(fn (array $key): bool => $key['columns'] === ['order_id']);
        if (! $hasForeign) {
            if ($exists) {
                Schema::table('local_sber_payments', fn (Blueprint $table) => $table->unsignedInteger('order_id')->change());
            }
            Schema::table('local_sber_payments', function (Blueprint $table): void {
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('local_sber_payments');
    }
};
