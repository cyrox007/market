<?php

namespace App\Services\Address;

use Filament\Notifications\Notification;

class WarehouseAddressOptions
{
    public function __construct(private readonly AddressDirectoryClient $directory) {}

    public function initial(string $level, ?string $parent, bool $compact = false): array
    {
        return $this->search($level, $parent, '', initial: true, compact: $compact);
    }

    public function search(string $level, ?string $parent, string $search, bool $initial = false, bool $compact = false): array
    {
        if (($level === 'localities' && mb_strlen(trim($search)) < 2 && ! ($parent && trim($search) === '') && ! $initial)
            || (! in_array($level, ['regions', 'localities'], true) && ! $parent)) {
            return [];
        }
        try {
            $items = $this->directory->options($level, array_filter([
                'parentExternalId' => $parent, 'q' => $level === 'regions' ? null : trim($search), 'limit' => $level === 'regions' ? 100 : 50,
            ], fn ($value) => $value !== null && $value !== ''));
            $options = [];
            foreach ($items as $item) {
                // The regions endpoint returns the complete small directory and
                // does not accept q. Filter names here, not the hierarchy label.
                if ($level === 'regions' && trim($search) !== ''
                    && ! str_contains(mb_strtolower($item['name'] ?? $item['label'] ?? ''), mb_strtolower(trim($search)))) {
                    continue;
                }
                if (empty($item['externalId']) || empty($item['label'])) {
                    continue;
                }
                // KLADR stores several house numbers in one row; never offer that
                // row as if it were one physical building.
                $numbers = $level === 'buildings' && ($item['source'] ?? null) === 'kladr'
                    ? array_filter(array_map('trim', explode(',', $item['name'] ?? '')))
                    : [null];
                foreach ($numbers as $number) {
                    if ($number !== null && trim($search) !== '' && ! str_contains(mb_strtolower($number), mb_strtolower(trim($search)))) {
                        continue;
                    }
                    if ($number !== null && preg_match('/\d\s*[-–]\s*\d/u', $number)) {
                        continue; // Ranges need GAR, not an invented individual house.
                    }
                    $id = $item['externalId'].($number !== null ? '::'.rawurlencode($number) : '');
                    $label = $number !== null
                        ? preg_replace('/'.preg_quote((string) $item['name'], '/').'$/u', $number, $item['label'])
                        : $item['label'];
                    $short = $level === 'buildings' ? 'д. '.($number ?? $item['name'] ?? $label) : ($item['name'] ?? $label);
                    if ($level === 'streets' && ! empty($item['name'])) {
                        $short = trim(($item['type'] ?? 'ул.').' '.$item['name']);
                    }
                    if ($level === 'localities' && $parent) {
                        $short = trim(($item['type'] ?? '').' '.($item['name'] ?? ''));
                        if ($short === '') {
                            $short = $label;
                        }
                        // The API label is the root-to-target hierarchy. Keep
                        // intermediate context but omit the selected region.
                        $parts = array_map('trim', explode(',', $label));
                        if (count($parts) > 2) {
                            array_shift($parts);
                            array_pop($parts);
                            $short .= ' ('.implode(', ', $parts).')';
                        }
                    }
                    $options[$id] = ($compact || ($level === 'localities' && $parent)) ? $short : $label;
                    \Illuminate\Support\Facades\Cache::put('address-directory:short-label:'.sha1($id), $short,
                        (int) config('address_directory.cache_ttl'));
                    \Illuminate\Support\Facades\Cache::put('address-directory:label:'.sha1($id), $label,
                        (int) config('address_directory.cache_ttl'));
                }
            }

            return $options;
        } catch (\Throwable $error) {
            report($error);
            Notification::make()->title('Адресный справочник недоступен')
                ->body('Повторите позже. Сохранённый адрес склада не изменён.')->warning()->send();

            return [];
        }
    }

    public function localityLabel(string $id): ?string
    {
        return \Illuminate\Support\Facades\Cache::get('address-directory:short-label:'.sha1($id))
            ?? $this->directory->cachedLabel($id);
    }
}
