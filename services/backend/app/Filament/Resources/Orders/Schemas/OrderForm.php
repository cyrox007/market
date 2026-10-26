<?php
namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...array_slice(OrderWorkspace::components(), 0, 1),
            Section::make('Покупатель и заметки')->description('Сохранение здесь изменяет только контакты и заметку. Адрес, состав заказа и суммы остаются прежними.')->schema([
                TextInput::make('contact_name')->label('Имя покупателя')->required()->maxLength(255),
                TextInput::make('contact_phone')->label('Телефон')->required()->maxLength(20),
                TextInput::make('contact_email')->label('Email')->email()->maxLength(255),
                Textarea::make('comment')->label('Комментарий к заказу')->maxLength(1000)->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
            ...array_slice(OrderWorkspace::components(), 1),
        ]);
    }
}