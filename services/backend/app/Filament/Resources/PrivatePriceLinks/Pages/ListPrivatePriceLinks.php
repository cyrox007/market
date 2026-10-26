<?php
namespace App\Filament\Resources\PrivatePriceLinks\Pages;
class ListPrivatePriceLinks extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Resources\PrivatePriceLinks\PrivatePriceLinkResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}
