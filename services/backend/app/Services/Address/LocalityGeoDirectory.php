<?php
namespace App\Services\Address;

/** All geography comes from the shared API, never a second SQLite classifier. */
class LocalityGeoDirectory
{
    public function __construct(private readonly AddressDirectoryClient $directory) {}

    public function regions(): array
    {
        return $this->directory->options('regions');
    }

    public function search(?string $region, string $query): array
    {
        if (! $region && mb_strlen(trim($query)) < 2) {
            return [];
        }
        return $this->directory->options('localities', array_filter([
            'parentExternalId' => $region, 'q' => trim($query), 'limit' => 50,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function find(string $id): ?array
    {
        $hierarchy = $this->directory->hierarchy($id);
        $target = $hierarchy['target'] ?? null;
        if (($target['kind'] ?? null) !== 'locality') {
            return null;
        }
        $region = collect($hierarchy['path'] ?? [])->firstWhere('kind', 'region');
        return array_merge($target, [
            'regionExternalId' => $region['externalId'] ?? null,
            'regionKladrCode' => $region['kladrCode'] ?? null,
        ]);
    }

    public function nearby(float $latitude, float $longitude): array
    {
        return $this->directory->nearby($latitude, $longitude);
    }
}
