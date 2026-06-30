<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_attributes') && !Schema::hasColumn('product_attributes', 'allow_custom_value')) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->boolean('allow_custom_value')->default(false)->after('is_use_in_variations');
            });
        }

        if (Schema::hasTable('product_variant_attributes') && !Schema::hasColumn('product_variant_attributes', 'custom_value')) {
            Schema::table('product_variant_attributes', function (Blueprint $table) {
                $table->string('custom_value', 500)->nullable()->after('attribute_value_id');
            });
            Schema::table('product_variant_attributes', function (Blueprint $table) {
                $table->unsignedBigInteger('attribute_value_id')->nullable()->change();
            });
        }

        if (!Schema::hasTable('product_variation_attribute_selection')) {
            Schema::create('product_variation_attribute_selection', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->comment('ID родительского товара (вариативного)');
                $table->unsignedBigInteger('attribute_id');
                $table->timestamps();
                $table->unique(['product_id', 'attribute_id'], 'pvas_prod_attr_uniq');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_attributes') && Schema::hasColumn('product_attributes', 'allow_custom_value')) {
            Schema::table('product_attributes', function (Blueprint $table) {
                $table->dropColumn('allow_custom_value');
            });
        }
        if (Schema::hasTable('product_variant_attributes')) {
            if (Schema::hasColumn('product_variant_attributes', 'custom_value')) {
                Schema::table('product_variant_attributes', function (Blueprint $table) {
                    $table->dropColumn('custom_value');
                });
            }
            Schema::table('product_variant_attributes', function (Blueprint $table) {
                $table->unsignedBigInteger('attribute_value_id')->nullable(false)->change();
            });
        }
        Schema::dropIfExists('product_variation_attribute_selection');
    }
};
