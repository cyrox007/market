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
        Schema::table('product_attributes', function (Blueprint $table) {
            if (!Schema::hasColumn('product_attributes', 'is_required')) {
                $table->boolean('is_required')
                    ->default(false)
                    ->after('is_filterable')
                    ->comment('Обязательная характеристика для заполнения в карточке товара');

                $table->index('is_required');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            if (Schema::hasColumn('product_attributes', 'is_required')) {
                $table->dropIndex(['is_required']);
                $table->dropColumn('is_required');
            }
        });
    }
};

