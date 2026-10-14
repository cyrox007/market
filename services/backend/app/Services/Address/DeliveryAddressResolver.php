<?php

namespace App\Services\Address;

use Illuminate\Validation\ValidationException;

class DeliveryAddressResolver
{
    public function __construct(private readonly AddressDirectoryClient $directory) {}

    public function resolve(string $externalId): array
    {
        $selectionId = $externalId;
        [$externalId, $number] = array_pad(explode('::', $externalId, 2), 2, null);
        $number = $number !== null ? rawurldecode($number) : null;
        $snapshot = $this->directory->hierarchy($externalId);
        $target = $snapshot['target'] ?? [];
        if (($target['kind'] ?? null) !== 'building' || ($target['externalId'] ?? null) !== $externalId) {
            throw ValidationException::withMessages(['directory_external_id' => 'Выберите конкретный дом или здание из справочника.']);
        }
        if (($target['source'] ?? null) === 'kladr') {
            $numbers = array_map('trim', explode(',', (string) ($target['name'] ?? '')));
            $number ??= count($numbers) === 1 ? $numbers[0] : null;
            if (! $number || ! in_array($number, $numbers, true) || preg_match('/\d\s*[-–]\s*\d/u', $number)) {
                throw ValidationException::withMessages(['directory_external_id' => 'Выберите отдельный номер дома, а не группу или диапазон КЛАДР.']);
            }
            $snapshot['label'] = preg_replace('/'.preg_quote($target['name'], '/').'$/u', $number, $snapshot['label'] ?? '');
            $snapshot['selectedBuildingNumber'] = $number;
        } elseif ($number !== null) {
            throw ValidationException::withMessages(['directory_external_id' => 'Номер здания ГАР нельзя подменять.']);
        }
        $snapshot['selectionId'] = $selectionId;
        $path = collect($snapshot['path'] ?? []);
        $city = $path->last(fn ($item) => ($item['kind'] ?? null) === 'locality');
        $street = $path->last(fn ($item) => ($item['kind'] ?? null) === 'street');
        if (! $city || empty($target['name']) || empty($snapshot['label'])) {
            throw ValidationException::withMessages(['directory_external_id' => 'У здания нет полной адресной иерархии.']);
        }

        return [
            'directory_external_id' => $selectionId,
            'gar_guid' => ($target['source'] ?? null) === 'gar' ? $externalId : null,
            'kladr_code' => $target['kladrCode'] ?? (($target['source'] ?? null) === 'kladr' ? $externalId : null),
            'city' => $city['name'],
            'street' => $street['name'] ?? '',
            'house' => $snapshot['selectedBuildingNumber'] ?? $target['name'],
            'address_snapshot' => $snapshot,
            // Warehouse delivery locations are not selectable address classifier objects.
            'shipping_location_id' => null,
        ];
    }
}
