<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'is_sellable_bundle')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('is_sellable_bundle')->default(false);
            });
        }
        if (! Schema::hasTable('sellable_bundle_components')) {
            Schema::create('sellable_bundle_components', function (Blueprint $table): void {
                $table->id();
                $table->intOrBigIntBasedOnRelated('product_id', Schema::connection(null), 'products.id');
                $table->intOrBigIntBasedOnRelated('component_product_id', Schema::connection(null), 'products.id');
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['product_id', 'component_product_id'], 'sellable_bundle_component_unique');
            });
        }
        // MySQL commits DDL before a later FK failure. Repair an interrupted run.
        foreach (['product_id' => 'cascade', 'component_product_id' => 'restrict'] as $column => $delete) {
            $foreign = collect(Schema::getForeignKeys('sellable_bundle_components'))
                ->first(fn (array $key): bool => $key['columns'] === [$column]);
            if (Schema::getColumnType('sellable_bundle_components', $column, true) !== Schema::getColumnType('products', 'id', true)) {
                if ($foreign) {
                    Schema::table('sellable_bundle_components', fn (Blueprint $table) => $table->dropForeign($foreign['name']));
                    $foreign = null;
                }
                Schema::table('sellable_bundle_components', fn (Blueprint $table) => $table->intOrBigIntBasedOnRelated($column, Schema::connection(null), 'products.id')->change());
            }
            if (! $foreign) {
                Schema::table('sellable_bundle_components', fn (Blueprint $table) => $table->foreign($column)->references('id')->on('products')->onDelete($delete));
            }
        }
        if (! Schema::hasColumn('orders', 'bundle_snapshot')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->json('bundle_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('bundle_snapshot'));
        Schema::dropIfExists('sellable_bundle_components');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_sellable_bundle'));
    }
};
