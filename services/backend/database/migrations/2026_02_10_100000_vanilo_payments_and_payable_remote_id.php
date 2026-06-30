<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
                $table->string('payable_type');
                $table->unsignedBigInteger('payable_id');
                $table->string('hash')->nullable()->unique();
                $table->string('remote_id')->nullable();
                $table->json('data')->nullable();
                $table->char('currency', 3);
                $table->decimal('amount', 15, 4);
                $table->decimal('amount_paid', 15, 4)->default(0);
                $table->string('status', 35);
                $table->string('status_message')->nullable();
                $table->string('subtype')->nullable();
                $table->timestamps();

                $table->index(['payable_type', 'payable_id']);
                $table->index('status');
            });
        }

        if (!Schema::hasTable('payment_history')) {
            Schema::create('payment_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_id');
                $table->string('old_status', 35)->nullable();
                $table->string('new_status', 35);
                $table->string('message')->nullable();
                $table->string('native_status')->nullable();
                $table->string('transaction_number')->nullable();
                $table->decimal('transaction_amount', 15, 4)->nullable();
                $table->timestamps();

                $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'payable_remote_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payable_remote_id')->nullable()->after('delivery_free_threshold');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'payable_remote_id')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('payable_remote_id'));
        }
        Schema::dropIfExists('payment_history');
        Schema::dropIfExists('payments');
    }
};
