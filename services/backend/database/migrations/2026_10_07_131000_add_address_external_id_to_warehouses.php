<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouses', 'address_external_id')) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->string('address_external_id', 128)->nullable()->after('address')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('warehouses', 'address_external_id')) {
            Schema::table('warehouses', fn (Blueprint $table) => $table->dropColumn('address_external_id'));
        }
    }
};
