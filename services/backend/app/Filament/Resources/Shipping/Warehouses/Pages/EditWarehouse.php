<?php

namespace App\Filament\Resources\Shipping\Warehouses\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Shipping\Warehouses\WarehouseResource;

class EditWarehouse extends EditRecord
{
    public static bool $formActionsAreSticky = false;

    public function getRelationManagers(): array
    {
        return []; // Delivery settings are rendered inside the Delivery tab.
    }

    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $snapshot = $data['address_snapshot'] ?? null;
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }
        $data['address_external_id'] = $snapshot['selectionId'] ?? $data['address_external_id'] ?? null;
        foreach (['region' => 'directory_region', 'locality' => 'directory_locality', 'street' => 'directory_street'] as $kind => $field) {
            $item = collect($snapshot['path'] ?? [])->last(fn (array $item): bool => ($item['kind'] ?? null) === $kind);
            $data[$field] = $item['externalId'] ?? null;
            if (isset($item['externalId'], $item['label'])) {
                \Illuminate\Support\Facades\Cache::put('address-directory:label:'.sha1($item['externalId']),
                    $item['label'], (int) config('address_directory.cache_ttl'));
            }
        }

        if (blank($data['latitude'] ?? null) || blank($data['longitude'] ?? null)) {
            $level = filled($data['address_external_id'] ?? null) ? 'building'
                : (filled($data['directory_street'] ?? null) ? 'street' : (filled($data['directory_locality'] ?? null) ? 'locality' : 'region'));
            $id = $data['address_external_id'] ?? $data['directory_street'] ?? $data['directory_locality'] ?? $data['directory_region'] ?? null;
            $data['map_centre'] = app(\App\Services\Address\WarehouseMapContext::class)->focus($level, $id, snapshot: is_array($snapshot) ? $snapshot : null);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(\App\Services\Address\WarehouseAddressResolver::class)->prepare($data, $this->getRecord());
    }
}
