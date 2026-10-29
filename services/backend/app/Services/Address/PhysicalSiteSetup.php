<?php

namespace App\Services\Address;

use App\Models\Inventory\PhysicalSite;
use App\Models\Inventory\Warehouse;
use App\Models\Page\Store;
use Illuminate\Validation\ValidationException;

class PhysicalSiteSetup
{
    public function create(array $data): PhysicalSite
    {
        $resolved = app(WarehouseAddressResolver::class)->prepare($data + ['source_type' => 'physical']);
        $city = AddressHierarchy::locality($resolved['address_snapshot'] ?? []);

        return PhysicalSite::create(array_merge($resolved, ['city' => $city['name'] ?? null]));
    }

    public function update(PhysicalSite $site, array $data): PhysicalSite
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($site, $data): PhysicalSite {
            $previous = new Warehouse($site->only(['address_external_id', 'address_snapshot', 'address', 'latitude', 'longitude']));
            $resolved = app(WarehouseAddressResolver::class)->prepare($data + ['source_type' => 'physical'], $previous);
            // Unchanged addresses preserve authoritative identifiers as well.
            $city = AddressHierarchy::locality($resolved['address_snapshot'] ?? $site->address_snapshot ?? []);
            $oldSelection = $site->address_snapshot['selectionId'] ?? $site->address_external_id;
            $newSelection = $resolved['address_snapshot']['selectionId'] ?? $oldSelection;
            $site->fill($resolved + ['city' => $city['name'] ?? $site->city])->save();
            foreach ($site->stores()->get() as $store) {
                $store->fill(['address' => $site->address, 'city' => $site->city, 'latitude' => $site->latitude,
                    'longitude' => $site->longitude, 'coordinates' => null])->save();
            }
            foreach ($site->warehouses()->get() as $warehouse) {
                $warehouse->fill($site->only(['address', 'address_external_id', 'address_snapshot', 'gar_guid', 'kladr_code']));
                if ($oldSelection !== $newSelection) {
                    $warehouse->fill(['latitude' => null, 'longitude' => null, 'coordinate_source' => null, 'coordinate_precision' => null]);
                }
                $warehouse->save();
            }

            return $site;
        });
    }

    public function store(array $data, ?Store $record = null): array
    {
        return \Illuminate\Support\Facades\DB::transaction(fn (): array => $this->prepareStore($data, $record));
    }

    private function prepareStore(array $data, ?Store $record): array
    {
        if (array_key_exists('phone', $data)) {
            $data['phone'] = \App\Support\PhoneNumber::normalize($data['phone']);
        }
        $mode = $data['store_stock_mode'] ?? (($data['warehouse_id'] ?? $record?->warehouse_id) ? 'warehouse' : 'showroom');
        unset($data['store_stock_mode']);
        $warehouseId = $mode === 'showroom' ? null : ($data['warehouse_id'] ?? null);
        $warehouse = $warehouseId ? Warehouse::query()->lockForUpdate()->find($warehouseId) : null;
        if ($mode === 'warehouse' && (! $warehouse || $warehouse->source_type !== 'physical' || $warehouse->stock_mode !== 'quantity' || ! $warehouse->is_active)) {
            throw ValidationException::withMessages(['warehouse_id' => 'Выберите действующий физический склад с фактическими остатками. Фабрика не является складом магазина.']);
        }
        $siteId = $data['physical_site_id'] ?? $record?->physical_site_id;
        $site = $siteId ? PhysicalSite::find($siteId) : null;
        if ($siteId && ! $site) {
            throw ValidationException::withMessages(['physical_site_id' => 'Площадка не найдена.']);
        }
        if ($warehouse) {
            if (! $warehouse->physical_site_id && $site && $this->sameBuilding($warehouse, $site)) {
                // Legacy warehouses already have an authoritative address. Link that
                // exact building; never move the warehouse or copy its inventory.
                $warehouse->physical_site_id = $site->id;
                $warehouse->save();
            }
            if (! $warehouse->physical_site_id || ($siteId && (int) $siteId !== (int) $warehouse->physical_site_id)) {
                throw ValidationException::withMessages(['warehouse_id' => 'Магазин с собственными остатками должен находиться на той же площадке, что и выбранный склад. Сначала укажите адрес площадки в складе.']);
            }
            $siteId = $warehouse->physical_site_id;
        }
        $site = $siteId ? PhysicalSite::find($siteId) : null;
        if ($siteId && ! $site) {
            throw ValidationException::withMessages(['physical_site_id' => 'Площадка не найдена.']);
        }
        if (! $site) {
            throw ValidationException::withMessages(['physical_site_id' => 'Выберите или создайте адрес магазина.']);
        }
        $data['warehouse_id'] = $warehouseId;
        // A legacy delivery region must never remain an alternative source of rules.
        $data['shipping_location_id'] = null;
        $data['physical_site_id'] = $siteId;
        if ($site) {
            // Compatibility snapshots; site remains the authoritative source.
            $data = array_merge($data, ['address' => $site->address, 'city' => $site->city,
                'latitude' => $site->latitude, 'longitude' => $site->longitude, 'coordinates' => null]);
        }

        return $data;
    }

    private function sameBuilding(Warehouse $warehouse, PhysicalSite $site): bool
    {
        $warehouseSelection = $warehouse->address_snapshot['selectionId'] ?? $warehouse->address_external_id;
        $siteSelection = $site->address_snapshot['selectionId'] ?? $site->address_external_id;
        if (! is_string($warehouseSelection) || $warehouseSelection !== $siteSelection) {
            return false;
        }

        // A GAR building UUID or an individually selected KLADR house. A group ID,
        // city name or similar address text is insufficient for automatic linking.
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $warehouseSelection)
            || (bool) preg_match('/^\d+::[^:]+$/u', $warehouseSelection);
    }
}
