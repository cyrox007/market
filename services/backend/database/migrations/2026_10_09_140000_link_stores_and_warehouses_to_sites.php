<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_sites', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('address_external_id', 128)->nullable()->index();
            $table->uuid('gar_guid')->nullable()->index();
            $table->string('kladr_code', 128)->nullable()->index();
            $table->json('address_snapshot')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('coordinate_source', 32)->nullable();
            $table->string('coordinate_precision', 32)->nullable();
            $table->timestamps();
        });
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->foreignId('physical_site_id')->nullable()->constrained('physical_sites')->restrictOnDelete();
        });
        Schema::table('stores', function (Blueprint $table): void {
            $table->foreignId('physical_site_id')->nullable()->constrained('physical_sites')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('physical_site_id');
        });
        Schema::table('warehouses', fn (Blueprint $table) => $table->dropConstrainedForeignId('physical_site_id'));
        Schema::dropIfExists('physical_sites');
    }
};
