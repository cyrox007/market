<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'external_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('external_id', 255)->nullable()->after('id')->comment('Внешний идентификатор (например, из 1С) для сопоставления при импорте');
                $table->index('external_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'external_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['external_id']);
                $table->dropColumn('external_id');
            });
        }
    }
};
