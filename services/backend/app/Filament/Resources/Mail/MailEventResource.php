<?php

namespace App\Filament\Resources\Mail;

use App\Filament\Resources\Mail\Pages\CreateMailEvent;
use App\Filament\Resources\Mail\Pages\EditMailEvent;
use App\Filament\Resources\Mail\Pages\ListMailEvents;
use App\Filament\Resources\Mail\Pages\ViewMailEvent;
use App\Filament\Resources\Mail\RelationManagers\MailEventTemplatesRelationManager;
use App\Filament\Resources\Mail\Schemas\MailEventForm;
use App\Filament\Resources\Mail\Schemas\MailEventInfolist;
use App\Filament\Resources\Mail\Tables\MailEventsTable;
use App\Models\Mail\MailEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MailEventResource extends Resource
{
    protected static ?string $model = MailEvent::class;

    protected static ?string $navigationLabel = 'Почтовые события';

    protected static ?string $modelLabel = 'Почтовое событие';

    protected static ?string $pluralModelLabel = 'Почтовые события';

    protected static ?string $policy = \App\Policies\MailEventPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MailEventForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MailEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailEventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MailEventTemplatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailEvents::route('/'),
            'create' => CreateMailEvent::route('/create'),
            'view' => ViewMailEvent::route('/{record}'),
            'edit' => EditMailEvent::route('/{record}/edit'),
        ];
    }

    /**
     * Проверка, должен ли ресурс регистрироваться в навигации
     */
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
