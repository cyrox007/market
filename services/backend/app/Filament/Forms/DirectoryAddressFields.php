<?php

namespace App\Filament\Forms;

use App\Services\Address\AddressDirectoryClient;
use App\Services\Address\WarehouseAddressOptions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** Shared directory hierarchy, building coordinates and map for stores and warehouses. */
class DirectoryAddressFields
{
    public static function make(): array
    {
        return [
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('directory_region'), 'регионы')
                                ->label('1. Регион')->searchable()->live()->dehydrated(false)
                                                                ->options(fn (): array => app(WarehouseAddressOptions::class)->initial('regions', null))
                                ->getSearchResultsUsing(fn (string $search): array => app(WarehouseAddressOptions::class)->search('regions', null, $search))
                                ->getOptionLabelUsing(fn ($value): ?string => $value ? app(AddressDirectoryClient::class)->cachedLabel($value) ?? $value : null)
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    foreach (['directory_locality', 'directory_street', 'address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude', 'map_centre'] as $field) {
                                        $set($field, null);
                                    }
                                    $set('map_centre', app(\App\Services\Address\WarehouseMapContext::class)->focus('region', $state));
                                }),
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('directory_locality'), 'населённые пункты', ['directory_region'])
                                ->options(fn (Get $get): array => $get('directory_region') ? app(WarehouseAddressOptions::class)->initial('localities', $get('directory_region')) : [])
                                ->disabled(fn (Get $get): bool => blank($get('directory_region')))
                                                                ->label('2. Населённый пункт')->searchable()->live()->dehydrated(false)
                                ->searchPrompt('Введите минимум две буквы города, посёлка или села')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => $get('directory_region') ? app(WarehouseAddressOptions::class)->search('localities', $get('directory_region'), $search) : [])
                                ->getOptionLabelUsing(fn ($value): ?string => $value ? app(WarehouseAddressOptions::class)->localityLabel((string) $value) ?? $value : null)
                                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                    foreach (['directory_street', 'address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude', 'map_centre'] as $field) {
                                        $set($field, null);
                                    }
                                    $set('map_centre', app(\App\Services\Address\WarehouseMapContext::class)->focus($state ? 'locality' : 'region', $state ?: $get('directory_region')));
                                })->helperText('Населённые пункты выбранного региона.'),
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('directory_street'), 'улицы', ['directory_locality'])
                                ->options(fn (Get $get): array => app(WarehouseAddressOptions::class)->initial('streets', $get('directory_locality'), compact: true))
                                                                ->label('3. Улица')->searchable()->live()->dehydrated(false)
                                ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search('streets', $get('directory_locality'), $search, compact: true))
                                ->getOptionLabelUsing(function ($value, ?\Illuminate\Database\Eloquent\Model $record): ?string {
                                    if (! $value) {
                                        return null;
                                    }
                                    $short = \Illuminate\Support\Facades\Cache::get('address-directory:short-label:'.sha1((string) $value));
                                    if ($short) {
                                        return $short;
                                    }
                                    $street = collect($record?->address_snapshot['path'] ?? [])->last(fn (array $item): bool => ($item['kind'] ?? null) === 'street' && ($item['externalId'] ?? null) === (string) $value);
                                    return filled($street['name'] ?? null) ? trim(($street['type'] ?? 'ул.').' '.$street['name']) : (string) $value;
                                })
                                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                    foreach (['address_external_id', 'address', 'gar_guid', 'kladr_code', 'latitude', 'longitude'] as $field) {
                                        $set($field, null);
                                    }
                                    $set('map_centre', app(\App\Services\Address\WarehouseMapContext::class)->focus($state ? 'street' : 'locality', $state ?: $get('directory_locality')));
                                })->helperText('Нет улицы — ищите здание без неё.'),
                            \App\Filament\Forms\DirectorySelectLoading::apply(Select::make('address_external_id'), 'здания', ['directory_locality', 'directory_street'])
                                ->options(fn (Get $get): array => app(WarehouseAddressOptions::class)->initial('buildings', $get('directory_street') ?: $get('directory_locality'), compact: true))
                                                                ->label('4. Дом / корпус / строение')
                                ->required()
                                ->searchable()
                                ->live()
                                ->searchPrompt('Введите номер дома')
                                ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                                ->noSearchResultsMessage('Здания не найдены. Проверьте населённый пункт, улицу и номер.')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search(
                                    'buildings', $get('directory_street') ?: $get('directory_locality'), $search, compact: true))
                                ->getOptionLabelUsing(function ($value, ?\Illuminate\Database\Eloquent\Model $record): ?string {
                                    if (blank($value)) {
                                        return null;
                                    }

                                    $short = \Illuminate\Support\Facades\Cache::get('address-directory:short-label:'.sha1((string) $value));
                                    if ($short) {
                                        return $short;
                                    }
                                    if (($record?->address_snapshot['selectionId'] ?? $record?->address_external_id) === (string) $value) {
                                        $number = $record?->address_snapshot['selectedBuildingNumber']
                                            ?? (str_contains((string) $value, '::') ? rawurldecode(explode('::', (string) $value, 2)[1]) : null)
                                            ?? $record?->address_snapshot['target']['name'] ?? null;

                                        return $number ? 'д. '.$number : (string) $value;
                                    }

                                    return (string) $value;
                                })
                                ->afterStateUpdated(function ($state, Set $set, Get $get, \Livewire\Component $livewire, Select $component): void {
                                    if (filled($state)) {
                                        $livewire->resetValidation($component->getStatePath());
                                    }
                                    $set('latitude', null);
                                    $set('longitude', null);
                                    $set('gar_guid', $state && preg_match('/^[0-9a-f-]{36}$/i', $state) ? $state : null);
                                    $set('kladr_code', null);
                                    if (blank($state)) {
                                        $set('map_centre', app(\App\Services\Address\WarehouseMapContext::class)->focus($get('directory_street') ? 'street' : 'locality', $get('directory_street') ?: $get('directory_locality')));
                                        $set('address', null);
                                        $set('gar_guid', null);
                                        $set('kladr_code', null);

                                        return;
                                    }

                                    $label = app(AddressDirectoryClient::class)->cachedLabel((string) $state);

                                    if ($label !== null) {
                                        $set('address', $label);
                                    }
                                    try {
                                        $resolved = app(\App\Services\Address\WarehouseAddressResolver::class)->resolve((string) $state);
                                        $point = app(\App\Services\Address\BuildingCoordinates::class)->find($resolved['address_snapshot']);
                                        $set('map_centre', app(\App\Services\Address\WarehouseMapContext::class)->focus('building', (string) $state, $get('map_centre')));
                                        $set('address', $resolved['address']);
                                        $set('latitude', $point['latitude'] ?? null);
                                        $set('longitude', $point['longitude'] ?? null);
                                    } catch (\Illuminate\Validation\ValidationException $error) {
                                        \Filament\Notifications\Notification::make()->title('Не удалось проверить здание. Повторите выбор.')->danger()->send();
                                    }
                                }),

                            Text::make(fn (Get $get): string => $get('address') ?: 'Выберите здание из справочника.')
                                ->columnSpanFull(),
                            \Filament\Forms\Components\Hidden::make('latitude'),
                            \Filament\Forms\Components\Hidden::make('longitude'),
                            \Filament\Forms\Components\Hidden::make('map_centre')->dehydrated(false),
                            \Filament\Forms\Components\ViewField::make('_warehouse_map')->view('filament.forms.warehouse-map')->dehydrated(false)->columnSpanFull(),
        ];
    }
}

