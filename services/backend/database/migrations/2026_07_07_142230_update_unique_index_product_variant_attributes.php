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
        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'attribute_id']);
            $table->unique(['product_id', 'attribute_id', 'attribute_value_id'], 'pva_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'attribute_id', 'attribute_value_id']);
            $table->unique(['product_id', 'attribute_id'], 'pva_unique');
        });
    }
};
