<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_transfer_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });
        \Illuminate\Support\Facades\DB::table('catalog_transfer_rows')->where('code', '')->where('status', 'error')->update(['status' => 'skipped', 'message' => 'Пропущено: в sku нет кода 1С.']);
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_transfer_settings');
    }
};
