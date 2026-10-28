<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_sellable_bundle')->default(false);
        });
        Schema::create('sellable_bundle_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'component_product_id'], 'sellable_bundle_component_unique');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->json('bundle_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('bundle_snapshot'));
        Schema::dropIfExists('sellable_bundle_components');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_sellable_bundle'));
    }
};
