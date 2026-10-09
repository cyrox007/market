<?php

namespace App\Services\Address;

use App\Models\Inventory\Warehouse;
use Illuminate\Validation\ValidationException;

class WarehouseAddressResolver
{
    public function __construct(private readonly AddressDirectoryClient $directory) {}

    public function resolve(string $id): array
    {
        [$externalId, $number] = array_pad(explode('::', $id, 2), 2, null);
        $number = $number !== null ? rawurldecode($number) : null;
        try {
            $snapshot = $this->directory->hierarchy($externalId);
        } catch (\Throwable $error) {
            report($error);
            throw ValidationException::withMessages(['address_external_id' => 'Адресный API недоступен. Адрес склада не изменён. Повторите позже.']);
        }
        $target = $snapshot['target'] ?? [];
        $isGar = ($target['source'] ?? null) === 'gar';
        if (($target['kind'] ?? null) !== 'building'
            || ! in_array($target['source'] ?? null, ['gar', 'kladr'], true)
            || ($target['externalId'] ?? null) !== $externalId
            || ($isGar && (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $externalId) || $number !== null))
            || empty($snapshot['label'])
            || ! collect($snapshot['path'] ?? [])->contains('kind', 'locality')) {
            throw ValidationException::withMessages(['address_external_id' => 'Выберите конкретное здание из справочника с полной адресной иерархией.']);
        }
        if (! $isGar) {
            $numbers = array_map('trim', explode(',', $target['name'] ?? ''));
            if ($number === null && count($numbers) === 1) {
                $number = $numbers[0];
            }
            if (! $number || ! in_array($number, $numbers, true) || preg_match('/\d\s*[-–]\s*\d/u', $number)) {
                throw ValidationException::withMessages(['address_external_id' => 'Выберите отдельный номер дома, а не группу или диапазон КЛАДР.']);
            }
            $snapshot['label'] = preg_replace('/'.preg_quote($target['name'], '/').'$/u', $number, $snapshot['label']);
            $snapshot['selectedBuildingNumber'] = $number;
        }
        $snapshot['selectionId'] = $id;

        return [
            'address_external_id' => $externalId,
            'gar_guid' => $isGar ? $externalId : null,
            'kladr_code' => $target['kladrCode'] ?? ($isGar ? null : $externalId),
            'address' => $snapshot['label'],
            'address_snapshot' => $snapshot,
        ];
    }

    public function prepare(array $data, ?Warehouse $record = null): array
    {
        $siteId = array_key_exists('physical_site_id', $data) ? $data['physical_site_id'] : $record?->physical_site_id;
        $site = $siteId ? \App\Models\Inventory\PhysicalSite::find($siteId) : null;
        if ($siteId && ! $site) {
            throw ValidationException::withMessages(['physical_site_id' => 'Площадка не найдена.']);
        }
        if ($record?->exists && $record->stores()->exists()
            && ((string) $siteId !== (string) $record->physical_site_id
                || ($data['source_type'] ?? $record->source_type) !== 'physical'
                || ($data['stock_mode'] ?? $record->stock_mode) !== 'quantity')) {
            throw ValidationException::withMessages(['physical_site_id' => 'Этот склад хранит остатки магазина. Сначала измените связь в магазине; нельзя незаметно перенести склад или превратить его в виртуальную фабрику.']);
        }
        $previous = $record?->address_snapshot['selectionId'] ?? $record?->address_external_id;
        $id = array_key_exists('address_external_id', $data) ? $data['address_external_id'] : $previous;
        if ($site) {
            $id = $site->address_snapshot['selectionId'] ?? $site->address_external_id;
        }
        $changed = $id !== $previous || (string) $siteId !== (string) $record?->physical_site_id;
        unset($data['directory_locality'], $data['directory_street']);
        // Derived values must never be accepted from a submitted form.
        unset($data['address'], $data['gar_guid'], $data['kladr_code'], $data['address_snapshot'], $data['coordinate_source'], $data['coordinate_precision']);
        if ($site) {
            $data = array_merge($data, $site->only(['address_external_id', 'gar_guid', 'kladr_code', 'address', 'address_snapshot']));
        } elseif ($id && ($changed || ! $record?->address_snapshot)) {
            $data = array_merge($data, $this->resolve($id));
        } elseif ($id && $record) {
            $data['address_external_id'] = $record->address_external_id;
            $data['address'] = $record->address;
        } elseif ($changed) {
            $data = array_merge($data, ['address' => null, 'gar_guid' => null, 'kladr_code' => null,
                'address_snapshot' => null, 'latitude' => null, 'longitude' => null]);
        }
        if ($changed && $record
            && is_numeric($data['latitude'] ?? null) && is_numeric($data['longitude'] ?? null)
            && (float) $data['latitude'] === (float) $record->latitude
            && (float) $data['longitude'] === (float) $record->longitude) {
            $data['latitude'] = null;
            $data['longitude'] = null;
        }
        $lat = array_key_exists('latitude', $data) ? $data['latitude'] : ($changed ? null : $record?->latitude);
        $lon = array_key_exists('longitude', $data) ? $data['longitude'] : ($changed ? null : $record?->longitude);
        if (($lat !== null && $lat !== '') || ($lon !== null && $lon !== '')) {
            if (! is_numeric($lat) || ! is_numeric($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                throw ValidationException::withMessages(['latitude' => 'Укажите обе координаты склада в допустимом диапазоне.']);
            }
            $data['coordinate_source'] = 'operator';
            $data['coordinate_precision'] = 'warehouse_point';
        } else {
            $data['latitude'] = null;
            $data['longitude'] = null;
            $data['coordinate_source'] = null;
            $data['coordinate_precision'] = null;
        }
        if (($data['source_type'] ?? $record?->source_type ?? 'physical') === 'physical' && ! $id) {
            throw ValidationException::withMessages(['address_external_id' => 'Выберите здание склада из адресного справочника.']);
        }

        return $data;
    }
}
