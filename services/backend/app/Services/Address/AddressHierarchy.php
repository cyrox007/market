<?php

namespace App\Services\Address;

final class AddressHierarchy
{
    public static function isLocality(array $node): bool
    {
        return ($node['kind'] ?? null) === 'locality'
            || (in_array($node['kind'] ?? null, ['region', 'area'], true)
                && in_array(mb_strtolower(trim($node['type'] ?? '', ' .')), ['г', 'город'], true));
    }

    public static function locality(array $snapshot): ?array
    {
        return collect($snapshot['path'] ?? [])->last(fn (array $node): bool => self::isLocality($node));
    }
}
