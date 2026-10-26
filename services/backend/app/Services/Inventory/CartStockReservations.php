<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartStockReservations
{
    public function owner(): ?string
    {
        if (request()->attributes->has('cart_reservation_owner')) {
            return request()->attributes->get('cart_reservation_owner');
        }
        if (! request()->hasSession()) { return null; }
        $session = request()->session();
        $key = $session->get('cart_reservation_owner');
        if (! $key) {
            $key = bin2hex(random_bytes(32));
            $session->put('cart_reservation_owner', $key);
        }
        $owner = hash_hmac('sha256', $key, (string) config('app.key'));
        request()->attributes->set('cart_reservation_owner', $owner);
        return $owner;
    }

    public function available(int $warehouseId, int $productId, float $physical, bool $excludeOwn = true): float
    {
        $q = DB::table('cart_stock_reservations')->where('warehouse_id', $warehouseId)->where('product_id', $productId)
            ->where('expires_at', '>', now());
        if ($excludeOwn && ($owner = $this->owner())) { $q->where('owner_hash', '!=', $owner); }
        $orders = (float) DB::table('product_warehouse_stocks')->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('reserved_quantity');
        return max(0, $physical - $orders - (float) $q->sum('quantity'));
    }

    public function reserve(Product $sku, int $quantity): void
    {
        $owner = $this->owner();
        if (! $owner) { throw ValidationException::withMessages(['cart' => 'Для резервирования необходима сессия корзины.']); }
        DB::transaction(function () use ($owner, $sku, $quantity): void {
            $previous = DB::table('cart_stock_reservations')->where('owner_hash', $owner)->where('product_id', $sku->id)->lockForUpdate()->first();
            $candidates = app(WarehouseStockResolver::class)->eligibleStocks($sku, null)->sortBy('warehouse_id');
            foreach ($candidates as $candidate) {
                $stock = ProductWarehouseStock::whereKey($candidate->id)->lockForUpdate()->first();
                if (! $stock || $this->available($stock->warehouse_id, $sku->id, (float) $stock->quantity) < $quantity) { continue; }
                $expires = $previous && $previous->expires_at > now()->toDateTimeString() ? $previous->expires_at : now()->addMinutes(30);
                DB::table('cart_stock_reservations')->updateOrInsert(['owner_hash' => $owner, 'product_id' => $sku->id],
                    ['warehouse_id' => $stock->warehouse_id, 'quantity' => $quantity, 'expires_at' => $expires, 'updated_at' => now(), 'created_at' => $previous?->created_at ?? now()]);
                \Illuminate\Support\Facades\Cache::forever('fulfillment_revision', (string) \Illuminate\Support\Str::uuid());
                return;
            }
            if (app(WarehouseStockResolver::class)->availableToOrder($sku, null)) {
                $this->release($sku->id); return; // Factory availability is not a finite physical stock.
            }
            throw ValidationException::withMessages(['cart' => 'Недостаточно свободного остатка на одном складе. Обновите корзину.']);
        }, 3);
    }

    public function release(?int $productId = null): void
    {
        if (! ($owner = $this->owner())) { return; }
        $q = DB::table('cart_stock_reservations')->where('owner_hash', $owner);
        if ($productId !== null) { $q->where('product_id', $productId); }
        if ($q->delete()) { \Illuminate\Support\Facades\Cache::forever('fulfillment_revision', (string) \Illuminate\Support\Str::uuid()); }
    }

    public function forProduct(int $id): ?object
    {
        return DB::table('cart_stock_reservations')->where('owner_hash', $this->owner())->where('product_id', $id)
            ->where('expires_at', '>', now())->first(['warehouse_id', 'quantity', 'expires_at']);
    }
}
