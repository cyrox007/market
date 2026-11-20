<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Warehouse;
use App\Services\Shipping\WarehouseCoverage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as ItemCollection;
use Illuminate\Support\Facades\DB;

/** Товар доступен в регионе, если есть в наличии на складе, который доставляет в точку покупателя. */
class RegionalAvailability
{
    /** @return Collection<int, Warehouse> склады с учётом остатков, покрывающие точку */
    public function warehouses(array $destination): Collection
    {
        if (! $destination['specified']) {
            return new Collection;
        }

        return Warehouse::where('is_active', true)->where('stock_mode', 'quantity')
            ->with(['physicalSite', 'deliveryProfiles'])->get()
            ->filter(fn (Warehouse $warehouse) => app(WarehouseCoverage::class)->firstProfile($warehouse, $destination) !== null)
            ->values();
    }

    /** Остаток строки `stock` за вычетом резервов и чужих удержаний в корзинах — от одной штуки. */
    public function whereInStock(QueryBuilder $stocks): QueryBuilder
    {
        $holds = DB::table('cart_stock_reservations as holds')->selectRaw('COALESCE(SUM(holds.quantity), 0)')
            ->whereColumn('holds.product_id', 'stock.product_id')->whereColumn('holds.warehouse_id', 'stock.warehouse_id')
            ->where('holds.expires_at', '>', now());
        if ($owner = app(CartStockReservations::class)->owner()) {
            $holds->where('holds.owner_hash', '!=', $owner);
        }

        return $stocks->whereRaw('stock.quantity - stock.reserved_quantity - ('.$holds->toSql().') >= 1', $holds->getBindings());
    }

    /** Ставит доступные в регионе первой ступенью порядка; остальные сортировки идут внутри групп. */
    public function orderFirst(Builder $query, array $warehouseIds): void
    {
        if ($warehouseIds === []) {
            return;
        }
        $stock = $this->stocks($warehouseIds)->selectRaw('1')->whereColumn('stock.product_id', 'products.id');
        $query->orderByRaw('EXISTS ('.$stock->toSql().') DESC', $stock->getBindings());
    }

    /** Доступные в регионе — первыми, внутри групп исходный порядок сохраняется. */
    public function putFirst(ItemCollection $products, array $availableIds): ItemCollection
    {
        return $products->sortBy(fn ($product) => in_array($product->id, $availableIds, true) ? 0 : 1)->values();
    }

    /** @return array<int, int> */
    public function availableIds(array $productIds, array $warehouseIds): array
    {
        if ($productIds === [] || $warehouseIds === []) {
            return [];
        }

        return $this->stocks($warehouseIds)->whereIn('stock.product_id', $productIds)
            ->distinct()->pluck('stock.product_id')->map(fn ($id) => (int) $id)->all();
    }

    private function stocks(array $warehouseIds): QueryBuilder
    {
        return $this->whereInStock(DB::table('product_warehouse_stocks as stock')->whereIn('stock.warehouse_id', $warehouseIds));
    }
}
