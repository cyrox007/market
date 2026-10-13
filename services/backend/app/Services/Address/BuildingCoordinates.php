<?php

namespace App\Services\Address;

use PDO;

class BuildingCoordinates
{
    public function find(array $snapshot): ?array
    {
        $target = $snapshot['target'] ?? [];
        if (($target['kind'] ?? null) !== 'building') {
            return null;
        }
        if (($target['coordinatePrecision'] ?? null) === 'building' && $this->valid($target['latitude'] ?? null, $target['longitude'] ?? null)) {
            return ['latitude' => (float) $target['latitude'], 'longitude' => (float) $target['longitude'], 'source' => $target['coordinateSource'] ?? 'directory'];
        }
        $file = config('address_directory.building_points_path');
        if (! is_file($file)) {
            return null;
        }
        $city = collect($snapshot['path'] ?? [])->last(fn (array $row): bool => ($row['kind'] ?? null) === 'locality');
        $street = collect($snapshot['path'] ?? [])->last(fn (array $row): bool => ($row['kind'] ?? null) === 'street');
        if (! $city || ! $street) {
            return null;
        }
        $centre = $this->centre($city);
        if (! $centre) {
            return null;
        }
        $number = $snapshot['selectedBuildingNumber'] ?? $target['name'] ?? '';
        if ($number === '' || str_contains($number, ',')) {
            return null; // Composite GAR numbers require explicit matching, never guess a corpus.
        }
        $name = preg_replace('/(^|\s)(улица|ул\.|ул|проспект|просп\.|пр-т)(\s|$)/ui', ' ', $street['name'] ?? '');
        $db = new PDO('sqlite:'.$file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA query_only = ON');
        $query = $db->prepare('SELECT * FROM points WHERE street = ? AND house = ? AND latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?');
        $query->execute([$this->normalize($name), $this->normalize($number), $centre[0] - .2, $centre[0] + .2, $centre[1] - .3, $centre[1] + .3]);
        $matches = array_values(array_filter($query->fetchAll(PDO::FETCH_ASSOC), function (array $point) use ($city): bool {
            return ! empty($point['city']) && $this->normalize($point['city']) === $this->normalize($city['name'] ?? '');
        }));
        if (! $matches) {
            return null;
        }
        $point = $matches[0];
        foreach ($matches as $other) {
            if (abs($other['latitude'] - $point['latitude']) > .0003 || abs($other['longitude'] - $point['longitude']) > .0003) {
                return null; // Ambiguous addresses are confirmed on the map instead.
            }
        }

        return ['latitude' => (float) $point['latitude'], 'longitude' => (float) $point['longitude'], 'source' => 'osm', 'sourceId' => $point['osm_id']];
    }

    public function centre(array $city): ?array
    {
        if ($this->valid($city['latitude'] ?? null, $city['longitude'] ?? null)) {
            return [(float) $city['latitude'], (float) $city['longitude']];
        }
        $code = $city['kladrCode'] ?? $city['externalId'] ?? '';
        $code = preg_match('/^\d{13}00$/', $code) ? substr($code, 0, 13) : $code;
        $file = config('address_directory.locality_points_path');
        if (! is_file($file) || ! preg_match('/^\d{13}$/', $code)) {
            return null;
        }
        $handle = fopen($file, 'r');
        try {
            while (($line = fgets($handle)) !== false) {
                $row = json_decode($line, true);
                if (($row['kladrCode'] ?? null) === $code && $this->valid($row['latitude'] ?? null, $row['longitude'] ?? null)) {
                    return [(float) $row['latitude'], (float) $row['longitude']];
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function valid(mixed $lat, mixed $lon): bool
    {
        return is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[\s.,]+/u', '', str_replace('ё', 'е', mb_strtolower($value)));
    }
}
