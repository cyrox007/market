<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_transfer_rows', function (Blueprint $table) {
            $table->boolean('assets_done')->default(false);
            $table->json('assets_result')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_transfer_rows', fn (Blueprint $table) => $table->dropColumn(['assets_done', 'assets_result']));
    }
};
