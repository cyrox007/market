<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', fn (Blueprint $table) => $table->boolean('pickup_enabled')->default(false));
        Schema::table('warehouse_delivery_profiles', fn (Blueprint $table) => $table->foreignId('services_location_id')->nullable()->constrained('shipping_locations')->restrictOnDelete());
        Schema::table('stores', function (Blueprint $table): void {
            $table->boolean('pickup_enabled')->default(true);
            $table->json('opening_hours')->nullable();
            $table->string('timezone')->default('Europe/Moscow');
        });
        Schema::table('taxons', fn (Blueprint $table) => $table->text('regional_text_template')->nullable());
    }

    public function down(): void
    {
        Schema::table('warehouses', fn (Blueprint $table) => $table->dropColumn('pickup_enabled'));
        Schema::table('warehouse_delivery_profiles', function (Blueprint $table): void {
            $table->dropForeign(['services_location_id']);
            $table->dropColumn('services_location_id');
        });
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['pickup_enabled', 'opening_hours', 'timezone']));
        Schema::table('taxons', fn (Blueprint $table) => $table->dropColumn('regional_text_template'));
    }
};
