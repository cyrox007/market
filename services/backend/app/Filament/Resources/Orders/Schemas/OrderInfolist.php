<?php
namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Покупатель')->schema([
                \Filament\Infolists\Components\TextEntry::make('contact_name')->label('Имя'),
                \Filament\Infolists\Components\TextEntry::make('contact_phone')->label('Телефон'),
                \Filament\Infolists\Components\TextEntry::make('contact_email')->label('Email'),
                \Filament\Infolists\Components\TextEntry::make('comment')->label('Комментарий'),
            ])->columns(3)->columnSpanFull(),
            ...OrderWorkspace::components(),
        ]);
    }
}
