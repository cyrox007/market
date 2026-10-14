<?php

namespace App\Services\Shipping;

use App\Services\Address\AddressDirectoryClient;
use Illuminate\Validation\ValidationException;

class DirectoryCoverage
{
    public function verify(array $territories): array
    {
        $verified = [];
        foreach ($territories as $territory) {
            $id = ($territory['locality_id'] ?? null) ?: (($territory['region_id'] ?? null) ?: ($territory['externalId'] ?? null));
            if (! $id) {
                throw ValidationException::withMessages(['directory_territories' => 'Выберите территорию из справочника.']);
            }
            try {
                $snapshot = app(AddressDirectoryClient::class)->hierarchy($id);
            } catch (\RuntimeException $error) {
                report($error);
                throw ValidationException::withMessages(['directory_territories' => 'Справочник недоступен. Покрытие не изменено, повторите позже.']);
            }
            $target = $snapshot['target'] ?? [];
            if (($target['externalId'] ?? null) !== $id || ! in_array($target['kind'] ?? null, ['region', 'locality'], true)) {
                throw ValidationException::withMessages(['directory_territories' => 'Покрытие должно быть регионом или населённым пунктом.']);
            }
            if (! empty($territory['locality_id']) && ! empty($territory['region_id'])
                && ! collect($snapshot['path'] ?? [])->contains('externalId', $territory['region_id'])) {
                throw ValidationException::withMessages(['directory_territories' => 'Населённый пункт не принадлежит выбранному региону.']);
            }
            $verified[$id] = [
                'externalId' => $id, 'kind' => $target['kind'], 'kladrCode' => $target['kladrCode'] ?? (ctype_digit($id) ? $id : null),
                'label' => $snapshot['label'] ?? $target['label'] ?? $target['name'],
                'region_id' => $target['kind'] === 'region' ? $id : ($territory['region_id'] ?? null),
                'locality_id' => $target['kind'] === 'locality' ? $id : null,
            ];
        }

        return array_values($verified);
    }

    public function matches(array $territories, array $snapshot): bool
    {
        foreach ($territories as $territory) {
            foreach ($snapshot['path'] ?? [] as $node) {
                if (($node['kind'] ?? null) !== ($territory['kind'] ?? null)) {
                    continue;
                }
                if (filled($node['externalId'] ?? null) && ($node['externalId'] === ($territory['externalId'] ?? null))) {
                    return true;
                }
                // Compatibility is based on classifier codes and level, never names.
                $nodeCode = $node['kladrCode'] ?? (ctype_digit((string) ($node['externalId'] ?? '')) ? $node['externalId'] : null);
                if (filled($territory['kladrCode'] ?? null) && filled($nodeCode)
                    && rtrim($territory['kladrCode'], '0') === rtrim($nodeCode, '0')) {
                    return true;
                }
            }
        }

        return false;
    }
}
