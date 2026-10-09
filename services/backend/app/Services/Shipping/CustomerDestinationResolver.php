<?php

namespace App\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Services\Address\LocalityGeoDirectory;

/** Compatibility bridge, never a guessed city or default warehouse destination. */
class CustomerDestinationResolver
{
    public function __construct(private readonly LocalityGeoDirectory $directory) {}

    public function resolve(array $locality): ?array
    {
        $id = $locality['kladrCode'] ?? $locality['externalId'];
        $regionId = $locality['regionKladrCode'] ?? null;
        if (! $regionId) {
            return null;
        }
        foreach ([['locality', [$id]], ['region', [$regionId, substr($regionId, 0, 2)]]] as [$type, $codes]) {
            $locations = ShippingLocation::query()->where('is_active', true)
                ->where('type', $type)->whereIn('code', $codes)->limit(2)->get();
            if ($locations->count() === 1) {
                return $locations->first()->only(['id', 'name', 'slug', 'type', 'parent_id']);
            }
            if ($locations->count() > 1) {
                return null;
            }
        }

        // Existing regions may lack their classifier code. Exact normalized region
        // names are safe only when the active region is unique (not city-name matching).
        $region = collect($this->directory->regions())->firstWhere('kladrCode', $regionId);
        if ($region) {
            $normalize = static fn (string $name): string => trim(preg_replace('/\s+/u', ' ',
                preg_replace('/\b(республика|область|край|автономная|автономный|округ)\b/u', '',
                    str_replace('ё', 'е', mb_strtolower($name)))));
            $matches = ShippingLocation::query()->where('is_active', true)->where('type', 'region')
                ->whereNull('code')->get()->filter(fn ($item): bool => $normalize($item->name) === $normalize($region['name']));
            if ($matches->count() === 1) {
                return $matches->first()->only(['id', 'name', 'slug', 'type', 'parent_id']);
            }
        }

        return null;
    }
}
