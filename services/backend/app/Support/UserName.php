<?php

namespace App\Support;

/**
 * Preserves user-entered multiword names: never attempt to split legacy full names.
 */
final class UserName
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', trim($value)));

        return $normalized === '' ? null : $normalized;
    }

    public static function format(string $firstName, string $lastName, ?string $patronymic = null): string
    {
        return implode(' ', array_filter([
            self::normalize($lastName),
            self::normalize($firstName),
            self::normalize($patronymic),
        ], static fn (?string $part): bool => $part !== null));
    }
}
