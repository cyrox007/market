<?php

namespace App\Filament\Resources\Mail\Schemas;

use App\Models\Mail\MailEvent;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;

class MailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Редактор электронного письма')
                ->description('Тема, содержимое и переменные. В письме о гостевом заказе защищённая ссылка добавляется системой автоматически и не хранится в шаблоне.')
                ->schema([
                    Select::make('mail_event_id')
                        ->label('Событие отправки')
                        ->relationship('mailEvent', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    TextInput::make('name')
                        ->label('Название шаблона в админке')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('subject')
                        ->label('Тема письма')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Например: Ваш заказ №{{order_number}} создан'),
                    RichEditor::make('body')
                        ->label('Содержимое письма')
                        ->required()
                        ->columnSpanFull()
                        ->helperText('Разрешены обычное форматирование, списки и ссылки. Плейсхолдеры вида {{contact_name}} заменяются при отправке.'),
                    Placeholder::make('available_variables')
                        ->label('Доступные переменные')
                        ->content(fn (Get $get): string => implode(', ', array_map(
                            fn (string $key): string => '{{'.$key.'}}',
                            self::variablesForEventId($get('mail_event_id'))
                        )) ?: 'Выберите событие выше')
                        ->columnSpanFull(),
                    Toggle::make('is_default')
                        ->label('Использовать по умолчанию')
                        ->helperText('При выборе этого шаблона другие шаблоны события перестают быть основными.')
                        ->default(false),
                    Toggle::make('is_active')
                        ->label('Активен')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    /** @return list<string> */
    public static function variablesForEventId(mixed $eventId): array
    {
        $code = MailEvent::find($eventId)?->code;

        return match ($code) {
            'order.created', 'order.created.guest' => [
                'contact_name', 'order_number', 'order_total', 'order_subtotal',
                'order_delivery_cost', 'order_assembly_cost', 'order_status',
                'payment_method', 'delivery_type', 'order_date',
            ],
            'order.status_changed' => ['contact_name', 'order_number', 'old_status', 'new_status', 'order_total'],
            'order.cancelled' => ['contact_name', 'order_number', 'order_total', 'order_date'],
            'user.registered' => ['user_name', 'user_email', 'login_url'],
            default => [],
        };
    }
}
