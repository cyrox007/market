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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_promotions')->default(true)->after('avatar');
            $table->boolean('sms_order_notifications')->default(true)->after('email_promotions');
            $table->boolean('push_notifications')->default(false)->after('sms_order_notifications');
            $table->boolean('new_product_notifications')->default(true)->after('push_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_promotions',
                'sms_order_notifications',
                'push_notifications',
                'new_product_notifications',
            ]);
        });
    }
};
