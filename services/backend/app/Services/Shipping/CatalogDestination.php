<?php

namespace App\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Services\Address\AddressDirectoryClient;
use App\Services\Address\BuildingCoordinates;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatalogDestination
{
    public function resolve(Request $request, ?ShippingLocation $location): array
    {
        $request->validate(['directory_external_id' => 'nullable|string|max:128',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180']);
        $snapshot = null;
        $point = $request->filled('latitude') && $request->filled('longitude')
            ? ['latitude' => (float) $request->input('latitude'), 'longitude' => (float) $request->input('longitude')] : null;
        if ($request->filled('directory_external_id')) {
            try {
                $snapshot = app(AddressDirectoryClient::class)->hierarchy($request->input('directory_external_id'));
            } catch (\RuntimeException $error) {
                report($error);
                throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => 'Адресный справочник временно недоступен.'], 503));
            }
            $target = $snapshot['target'] ?? [];
            if (! in_array($target['kind'] ?? null, ['region', 'locality', 'building'], true)) {
                throw ValidationException::withMessages(['directory_external_id' => 'Выберите регион, населённый пункт или здание.']);
            }
            if (($target['kind'] ?? null) === 'building') {
                $point ??= app(BuildingCoordinates::class)->find($snapshot);
            } elseif (($target['kind'] ?? null) === 'locality' && isset($target['latitude'], $target['longitude'])) {
                $point ??= ['latitude' => (float) $target['latitude'], 'longitude' => (float) $target['longitude']];
            }
        }

        return app(WarehouseCoverage::class)->destination($location, $snapshot, $point);
    }
}
