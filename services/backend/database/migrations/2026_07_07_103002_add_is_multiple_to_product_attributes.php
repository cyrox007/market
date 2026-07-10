<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->boolean('is_multiple')->default(false)->after('allow_custom_value');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->dropColumn('is_multiple');
            });
        });
    }
};
