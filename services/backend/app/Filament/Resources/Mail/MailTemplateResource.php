<?php

namespace App\Filament\Resources\Mail;

use App\Filament\Resources\Mail\Pages\CreateMailTemplate;
use App\Filament\Resources\Mail\Pages\EditMailTemplate;
use App\Filament\Resources\Mail\Pages\ListMailTemplates;
use App\Filament\Resources\Mail\Schemas\MailTemplateForm;
use App\Filament\Resources\Mail\Tables\MailTemplatesTable;
use App\Models\Mail\MailEventTemplate;
use App\Policies\MailEventTemplatePolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MailTemplateResource extends Resource
{
    protected static ?string $model = MailEventTemplate::class;
    protected static ?string $policy = MailEventTemplatePolicy::class;
    protected static ?string $navigationLabel = 'Шаблоны писем';
    protected static ?string $modelLabel = 'Шаблон письма';
    protected static ?string $pluralModelLabel = 'Шаблоны писем';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static string|UnitEnum|null $navigationGroup = 'Настройки';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return MailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailTemplates::route('/'),
            'create' => CreateMailTemplate::route('/create'),
            'edit' => EditMailTemplate::route('/{record}/edit'),
        ];
    }
}
