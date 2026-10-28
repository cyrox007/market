<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sellable_bundle_groups')) {
        Schema::create('sellable_bundle_groups', function (Blueprint $table): void {
            $table->id();
            $table->intOrBigIntBasedOnRelated('product_id', Schema::connection(null), 'products.id');
            $table->intOrBigIntBasedOnRelated('component_group_id', Schema::connection(null), 'products.id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'component_group_id'], 'sellable_bundle_group_unique');
        });
        }
        // Repair MySQL DDL left by an interrupted foreign-key creation.
        foreach (['product_id' => 'cascade', 'component_group_id' => 'restrict'] as $column => $delete) {
            $foreign = collect(Schema::getForeignKeys('sellable_bundle_groups'))
                ->first(fn (array $key): bool => $key['columns'] === [$column]);
            if (Schema::getColumnType('sellable_bundle_groups', $column, true) !== Schema::getColumnType('products', 'id', true)) {
                if ($foreign) {
                    Schema::table('sellable_bundle_groups', fn (Blueprint $table) => $table->dropForeign($foreign['name']));
                    $foreign = null;
                }
                Schema::table('sellable_bundle_groups', fn (Blueprint $table) =>
                    $table->intOrBigIntBasedOnRelated($column, Schema::connection(null), 'products.id')->change());
            }
            if (! $foreign) {
                Schema::table('sellable_bundle_groups', fn (Blueprint $table) =>
                    $table->foreign($column)->references('id')->on('products')->onDelete($delete));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sellable_bundle_groups');
    }
};
