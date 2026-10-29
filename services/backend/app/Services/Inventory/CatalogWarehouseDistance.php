<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Warehouse;
use App\Services\Shipping\WarehouseCoverage;
use App\Services\Shipping\WarehouseDistance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CatalogWarehouseDistance
{
    public function order(Builder $roots, Builder $matchingSkus, array $destination): void
    {
        $distances = [];
        foreach (Warehouse::where('is_active', true)->where('stock_mode', 'quantity')
            ->with(['physicalSite', 'deliveryProfiles'])->get() as $warehouse) {
            $covered = app(WarehouseCoverage::class)->firstProfile($warehouse, $destination) !== null;
            if (! $covered || ! $destination['point']) {
                continue;
            }
            $distance = app(WarehouseDistance::class)->kilometres($warehouse, $destination['point']['latitude'], $destination['point']['longitude']);
            if ($distance !== null) {
                $distances[$warehouse->id] = $distance;
            }
        }
        if (! $distances) {
            $roots->select('products.*')->selectRaw('NULL AS nearest_distance_km')->orderBy('products.id');

            return;
        }
        $cases = implode(' ', array_fill(0, count($distances), 'WHEN ? THEN ?'));
        $bindings = [];
        foreach ($distances as $id => $distance) {
            array_push($bindings, $id, $distance);
        }
        $skus = (clone $matchingSkus)->select('products.id', 'products.parent_product_id');
        $nearest = DB::query()->fromSub($skus, 'eligible_sku')
            ->join('product_warehouse_stocks as stock', 'stock.product_id', '=', 'eligible_sku.id')
            ->whereIn('stock.warehouse_id', array_keys($distances))
            ->selectRaw('eligible_sku.id AS root_id')
            ->selectRaw('MIN(CASE stock.warehouse_id '.$cases.' END) AS distance_km', $bindings)
            ->groupBy('eligible_sku.id');
        $holds = DB::table('cart_stock_reservations as holds')->selectRaw('COALESCE(SUM(holds.quantity), 0)')
            ->whereColumn('holds.product_id', 'stock.product_id')->whereColumn('holds.warehouse_id', 'stock.warehouse_id')
            ->where('holds.expires_at', '>', now());
        if ($owner = app(CartStockReservations::class)->owner()) { $holds->where('holds.owner_hash', '!=', $owner); }
        $nearest->whereRaw('stock.quantity - stock.reserved_quantity - ('.$holds->toSql().') >= 1', $holds->getBindings());
        $roots->leftJoinSub($nearest, 'nearest_source', 'nearest_source.root_id', '=', 'products.id')
            ->select('products.*')->addSelect('nearest_source.distance_km as nearest_distance_km')
            ->orderByRaw('nearest_source.distance_km IS NULL')->orderBy('nearest_source.distance_km')->orderBy('products.id');
    }
}
