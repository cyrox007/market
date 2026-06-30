<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'address_snapshot')) {
                $table->text('address_snapshot')
                    ->nullable()
                    ->after('address_id')
                    ->comment('Снимок адреса на момент оформления заказа');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'address_snapshot')) {
                $table->dropColumn('address_snapshot');
            }
        });
    }
};
