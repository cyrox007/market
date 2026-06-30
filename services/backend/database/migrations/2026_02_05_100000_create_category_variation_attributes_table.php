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
        Schema::create('category_variation_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->comment('ID категории (taxon_id)');
            $table->unsignedBigInteger('attribute_id')->comment('ID атрибута вариации');
            $table->timestamps();

            $table->unique(['category_id', 'attribute_id'], 'cat_var_attr_unique');
            $table->index('category_id');
            $table->index('attribute_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_variation_attributes');
    }
};
