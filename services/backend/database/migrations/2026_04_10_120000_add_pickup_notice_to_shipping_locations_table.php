<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_locations', function (Blueprint $table) {
            $table->text('pickup_notice')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_locations', function (Blueprint $table) {
            $table->dropColumn('pickup_notice');
        });
    }
};
