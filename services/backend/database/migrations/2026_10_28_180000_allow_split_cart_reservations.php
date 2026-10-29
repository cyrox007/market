<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('cart_stock_reservations', function (Blueprint $table): void {
            $table->dropUnique(['owner_hash', 'product_id']);
            $table->unique(['owner_hash', 'product_id', 'warehouse_id'], 'cart_owner_sku_source_unique');
        });
    }
    public function down(): void {
        // Multiple live allocations cannot safely be collapsed into one source.
        if (\Illuminate\Support\Facades\DB::table('cart_stock_reservations')->select('owner_hash', 'product_id')->groupBy('owner_hash', 'product_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new \RuntimeException('Release split cart reservations before rollback.');
        }
        Schema::table('cart_stock_reservations', function (Blueprint $table): void {
            $table->dropUnique('cart_owner_sku_source_unique');
            $table->unique(['owner_hash', 'product_id']);
        });
    }
};
