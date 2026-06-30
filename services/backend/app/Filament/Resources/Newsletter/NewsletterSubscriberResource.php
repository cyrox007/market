<?php

namespace App\Filament\Resources\Newsletter;

use App\Filament\Resources\Newsletter\Pages\CreateNewsletterSubscriber;
use App\Filament\Resources\Newsletter\Pages\EditNewsletterSubscriber;
use App\Filament\Resources\Newsletter\Pages\ListNewsletterSubscribers;
use App\Filament\Resources\Newsletter\Schemas\NewsletterSubscriberForm;
use App\Filament\Resources\Newsletter\Tables\NewsletterSubscribersTable;
use App\Models\Newsletter\NewsletterSubscriber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationLabel = 'Подписчики';

    protected static ?string $modelLabel = 'Подписчик';

    protected static ?string $pluralModelLabel = 'Подписчики';

    protected static ?string $policy = \App\Policies\NewsletterSubscriberPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return NewsletterSubscriberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscribersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscribers::route('/'),
            'create' => CreateNewsletterSubscriber::route('/create'),
            'edit' => EditNewsletterSubscriber::route('/{record}/edit'),
        ];
    }
}
