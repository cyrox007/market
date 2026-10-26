<?php

namespace App\Services\Address;

final class FulfillmentAddress
{
    /** Use the immutable order hierarchy, never a fresh or client-supplied address lookup. */
    public function integrationCodes(?array $snapshot): array
    {
        $building = filled($snapshot['kladr_code'] ?? null) ? (string) $snapshot['kladr_code'] : null;
        $nearest = collect($snapshot['path'] ?? [])->reverse()->first(
            fn (array $node): bool => filled($node['kladrCode'] ?? null)
                || (($node['source'] ?? null) === 'kladr' && filled($node['externalId'] ?? null))
        );

        return [
            'addressGarGuid' => $snapshot['gar_guid'] ?? null,
            'addressBuildingKladrCode' => $building,
            'addressKladrCode' => $building ?? ($nearest ? (string) (filled($nearest['kladrCode'] ?? null) ? $nearest['kladrCode'] : $nearest['externalId']) : null),
            'addressKladrLevel' => $building !== null ? 'building' : ($nearest['kind'] ?? null),
        ];
    }

    /** Server-resolved immutable data; callers never pass client-supplied codes here. */
    public function snapshot(?array $hierarchy, array $details = []): ?array
    {
        if (empty($hierarchy['target']['externalId'])) {
            return null;
        }
        $target = $hierarchy['target'];

        return [
            'version' => 1,
            'external_id' => $target['externalId'],
            'gar_guid' => ($target['source'] ?? null) === 'gar' ? $target['externalId'] : null,
            'object_id' => isset($target['objectId']) ? (string) $target['objectId'] : null,
            'kladr_code' => $target['kladrCode'] ?? (($target['source'] ?? null) === 'kladr' ? $target['externalId'] : null),
            'source' => $target['source'] ?? null,
            'label' => $hierarchy['label'] ?? $target['label'] ?? null,
            'path' => array_values($hierarchy['path'] ?? []),
            'details' => array_intersect_key($details, array_flip(['apartment', 'room', 'entrance', 'floor', 'intercom', 'comment'])),
            'latitude' => $target['latitude'] ?? null,
            'longitude' => $target['longitude'] ?? null,
            'coordinate_source' => $target['coordinateSource'] ?? null,
            'coordinate_precision' => $target['coordinatePrecision'] ?? null,
        ];
    }
}
