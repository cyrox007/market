<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                // Добавляем поле is_active если его нет
                if (!Schema::hasColumn('payment_methods', 'is_active')) {
                    $table->boolean('is_active')->default(true)->comment('Активен ли метод оплаты')->after('is_enabled');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payment_methods')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                if (Schema::hasColumn('payment_methods', 'is_active')) {
                    $table->dropColumn('is_active');
                }
            });
        }
    }
};
