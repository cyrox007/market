<?php

namespace App\Filament\Resources\Shipping\Warehouses\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Shipping\Warehouses\WarehouseResource;

class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $snapshot = $data['address_snapshot'] ?? null;
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }
        $data['address_external_id'] = $snapshot['selectionId'] ?? $data['address_external_id'] ?? null;
        foreach (['locality' => 'directory_locality', 'street' => 'directory_street'] as $kind => $field) {
            $item = collect($snapshot['path'] ?? [])->last(fn (array $item): bool => ($item['kind'] ?? null) === $kind);
            $data[$field] = $item['externalId'] ?? null;
            if (isset($item['externalId'], $item['label'])) {
                \Illuminate\Support\Facades\Cache::put('address-directory:label:'.sha1($item['externalId']),
                    $item['label'], (int) config('address_directory.cache_ttl'));
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(\App\Services\Address\WarehouseAddressResolver::class)->prepare($data, $this->getRecord());
    }
}
