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
        if (Schema::hasTable('product_attributes') && !Schema::hasColumn('product_attributes', 'is_use_in_variations')) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->boolean('is_use_in_variations')->default(false)->after('is_filterable')
                    ->comment('Участвует в торговых предложениях (вариациях)');
                $table->index('is_use_in_variations');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_attributes') && Schema::hasColumn('product_attributes', 'is_use_in_variations')) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->dropIndex(['is_use_in_variations']);
                $table->dropColumn('is_use_in_variations');
            });
        }
    }
};
