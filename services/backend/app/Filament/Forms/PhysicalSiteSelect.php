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
            TextInput::make('name')->label('Название адреса')->default('Магазин')->required()->maxLength(255)->columnSpanFull()
                ->helperText('Название для списка сохранённых адресов.'),
            ...DirectoryAddressFields::make(),
        ];

        return Select::make('physical_site_id')->label('Адрес площадки — магазин / склад')
            ->relationship('physicalSite', 'name')->searchable()->preload()->live()
            ->getOptionLabelFromRecordUsing(fn (PhysicalSite $record): string => $record->name.' — '.$record->address)
            ->helperText('Необязательно: выберите существующую площадку вместо повторного ввода адреса. «+» создаёт площадку, карандаш меняет её адрес для всех связанных магазинов и складов.')
            ->createOptionForm(fn (\Filament\Schemas\Schema $schema) => $schema->components($fields)->columns(2))
            ->editOptionForm(fn (\Filament\Schemas\Schema $schema) => $schema->components($fields)->columns(2))
            ->fillEditOptionActionFormUsing(function (Select $component): array {
                $site = PhysicalSite::findOrFail($component->getState());
                $data = $site->toArray();
                $data['address_external_id'] = $site->address_snapshot['selectionId'] ?? $site->address_external_id;
                foreach (['region' => 'directory_region', 'locality' => 'directory_locality', 'street' => 'directory_street'] as $kind => $field) {
                    $item = collect($site->address_snapshot['path'] ?? [])->last(fn (array $row): bool => ($row['kind'] ?? null) === $kind);
                    $data[$field] = $item['externalId'] ?? null;
                }

                return $data;
            })
            ->updateOptionUsing(fn (array $data, Select $component) => app(PhysicalSiteSetup::class)->update(PhysicalSite::findOrFail($component->getState()), $data))
            ->createOptionUsing(fn (array $data): int => app(PhysicalSiteSetup::class)->create($data)->id);
    }
}
