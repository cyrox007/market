<?php

namespace App\Services\Address;

use Filament\Notifications\Notification;

class WarehouseAddressOptions
{
    public function __construct(private readonly AddressDirectoryClient $directory) {}

    public function search(string $level, ?string $parent, string $search): array
    {
        if (($level === 'localities' && mb_strlen(trim($search)) < 2)
            || ($level !== 'localities' && ! $parent)) {
            return [];
        }
        try {
            $items = $this->directory->options($level, array_filter([
                'parentExternalId' => $parent, 'q' => trim($search), 'limit' => 50,
            ], fn ($value) => $value !== null && $value !== ''));
            $options = [];
            foreach ($items as $item) {
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
                    $options[$id] = $label;
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
}
