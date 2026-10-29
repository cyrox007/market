<?php

namespace App\Services\Inventory;

use App\Models\Page\Store;
use App\Services\Address\AddressHierarchy;

/** Customer contacts are selected independently of stock and fulfillment. */
class LocalStoreResolver
{
    public function resolve(array $destination): ?Store
    {
        $locality = AddressHierarchy::locality(['path' => $destination['path'] ?? []]);
        if (blank($locality['externalId'] ?? null)) {
            return null;
        }

        // Never match names: different regions can have identically named towns.
        return Store::active()->with(['physicalSite', 'warehouse.physicalSite'])->ordered()->get()
            ->first(function (Store $store) use ($locality): bool {
                $node = AddressHierarchy::locality($store->physicalSite?->address_snapshot ?? []);

                return filled($node['externalId'] ?? null)
                    && (string) $node['externalId'] === (string) $locality['externalId']
                    && ($node['source'] ?? null) === ($locality['source'] ?? null);
            });
    }
}
