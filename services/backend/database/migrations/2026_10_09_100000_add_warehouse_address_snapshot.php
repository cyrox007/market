<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', fn (Blueprint $table) => $table->text('address')->nullable()->change());
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->uuid('gar_guid')->nullable()->index();
            $table->string('kladr_code', 128)->nullable()->index();
            $table->json('address_snapshot')->nullable();
            $table->string('coordinate_source', 32)->nullable();
            $table->string('coordinate_precision', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropIndex(['gar_guid']);
            $table->dropIndex(['kladr_code']);
            $table->dropColumn(['gar_guid', 'kladr_code', 'address_snapshot', 'coordinate_source', 'coordinate_precision']);
        });
    }
};
