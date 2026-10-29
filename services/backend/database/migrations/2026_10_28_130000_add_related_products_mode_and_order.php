<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('related_products_mode', 16)->nullable();
        });
        Schema::table('product_related_products', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('product_related_products', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('related_products_mode');
        });
    }
};
