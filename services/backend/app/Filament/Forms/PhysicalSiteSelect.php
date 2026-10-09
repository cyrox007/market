<?php

namespace App\Filament\Forms;

use App\Models\Inventory\PhysicalSite;
use App\Services\Address\AddressDirectoryClient;
use App\Services\Address\PhysicalSiteSetup;
use App\Services\Address\WarehouseAddressOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class PhysicalSiteSelect
{
    public static function make(): Select
    {
        $fields = [
            TextInput::make('name')->label('Как назвать это место')->placeholder('Например: Головной офис, Чаплыгин')->required()->maxLength(255),
            Select::make('directory_locality')->label('1. Город / посёлок / село')->searchable()->live()->required()
                ->getSearchResultsUsing(fn (string $search): array => app(WarehouseAddressOptions::class)->search('localities', null, $search))
                ->getOptionLabelUsing(fn ($value): ?string => $value ? app(AddressDirectoryClient::class)->cachedLabel($value) ?? $value : null)
                ->afterStateUpdated(function (Set $set): void {
                    foreach (['directory_street', 'address_external_id', 'latitude', 'longitude'] as $field) {
                        $set($field, null);
                    }
                }),
            Select::make('directory_street')->label('2. Улица (если есть)')->searchable()->live()
                ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search('streets', $get('directory_locality'), $search))
                ->getOptionLabelUsing(fn ($value): ?string => $value ? app(AddressDirectoryClient::class)->cachedLabel($value) ?? $value : null)
                ->afterStateUpdated(function (Set $set): void {
                    foreach (['address_external_id', 'latitude', 'longitude'] as $field) {
                        $set($field, null);
                    }
                }),
            Select::make('address_external_id')->label('3. Дом / корпус / строение')->searchable()->required()
                ->disabled(fn (Get $get): bool => blank($get('directory_locality')))
                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(WarehouseAddressOptions::class)->search('buildings', $get('directory_street') ?: $get('directory_locality'), $search))
                ->getOptionLabelUsing(fn ($value): ?string => $value ? app(AddressDirectoryClient::class)->cachedLabel($value) ?? $value : null),
            TextInput::make('latitude')->label('Широта фактической точки')->numeric()->minValue(-90)->maxValue(90)->requiredWith('longitude'),
            TextInput::make('longitude')->label('Долгота фактической точки')->numeric()->minValue(-180)->maxValue(180)->requiredWith('latitude')
                ->helperText('Точка на карте, не центр города. Если неизвестна, оставьте обе координаты пустыми.'),
        ];

        return Select::make('physical_site_id')->label('Адрес площадки — магазин / склад')
            ->relationship('physicalSite', 'name')->searchable()->preload()->live()
            ->getOptionLabelFromRecordUsing(fn (PhysicalSite $record): string => $record->name.' — '.$record->address)
            ->helperText('Общий адрес магазина и склада. «+» — создать, карандаш — изменить для всех связанных объектов.')
            ->createOptionForm($fields)->editOptionForm($fields)
            ->fillEditOptionActionFormUsing(function (Select $component): array {
                $site = PhysicalSite::findOrFail($component->getState());
                $data = $site->toArray();
                $data['address_external_id'] = $site->address_snapshot['selectionId'] ?? $site->address_external_id;
                foreach (['locality' => 'directory_locality', 'street' => 'directory_street'] as $kind => $field) {
                    $item = collect($site->address_snapshot['path'] ?? [])->last(fn (array $row): bool => ($row['kind'] ?? null) === $kind);
                    $data[$field] = $item['externalId'] ?? null;
                }

                return $data;
            })
            ->updateOptionUsing(fn (array $data, Select $component) => app(PhysicalSiteSetup::class)->update(PhysicalSite::findOrFail($component->getState()), $data))
            ->createOptionUsing(fn (array $data): int => app(PhysicalSiteSetup::class)->create($data)->id);
    }
}
