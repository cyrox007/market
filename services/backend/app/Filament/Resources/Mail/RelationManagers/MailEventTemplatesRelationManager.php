<?php

namespace App\Filament\Resources\Mail\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MailEventTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'templates';

    protected static ?string $title = 'Шаблоны писем';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Проверка прав доступа для RelationManager
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view mail_events') ?? false;
    }

    public static function canCreateForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('update mail_events') ?? false;
    }

    /**
     * Формат переменных для отображения
     */
    protected static function formatVariables($state): string
    {
        if (empty($state)) {
            return '';
        }
        if (is_array($state)) {
            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            return $state;
        }
        return '';
    }

    /**
     * Дегидратация переменных для сохранения
     */
    protected static function dehydrateVariables($state)
    {
        if (empty($state)) {
            return null;
        }
        if (is_array($state)) {
            return $state;
        }
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return null;
        }
        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('subject')
                    ->label('Тема письма')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn($record) => $record->subject),
                IconColumn::make('is_default')
                    ->label('По умолчанию')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        TextInput::make('name')
                            ->label('Название шаблона')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subject')
                            ->label('Тема письма')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Используйте {{переменная}} для подстановки значений'),
                        Textarea::make('body')
                            ->label('Текст письма')
                            ->required()
                            ->rows(10)
                            ->helperText('Используйте {{переменная}} для подстановки значений'),
                        Textarea::make('variables')
                            ->label('Доступные переменные (JSON)')
                            ->rows(5)
                            ->helperText('Список доступных переменных в формате JSON массива строк, например: ["order_number", "order_total"]')
                            ->formatStateUsing(fn($state) => static::formatVariables($state))
                            ->dehydrateStateUsing(fn($state) => static::dehydrateVariables($state)),
                        Toggle::make('is_default')
                            ->label('Шаблон по умолчанию')
                            ->default(false)
                            ->helperText('Если включено, этот шаблон будет использоваться по умолчанию для этого события'),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('name')
                            ->label('Название шаблона')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subject')
                            ->label('Тема письма')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Используйте {{переменная}} для подстановки значений'),
                        Textarea::make('body')
                            ->label('Текст письма')
                            ->required()
                            ->rows(10)
                            ->helperText('Используйте {{переменная}} для подстановки значений'),
                        Textarea::make('variables')
                            ->label('Доступные переменные (JSON)')
                            ->rows(5)
                            ->helperText('Список доступных переменных в формате JSON массива строк, например: ["order_number", "order_total"]')
                            ->formatStateUsing(fn($state) => static::formatVariables($state))
                            ->dehydrateStateUsing(fn($state) => static::dehydrateVariables($state)),
                        Toggle::make('is_default')
                            ->label('Шаблон по умолчанию')
                            ->default(false)
                            ->helperText('Если включено, этот шаблон будет использоваться по умолчанию для этого события'),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Шаблоны писем для события: ' . $ownerRecord->name;
    }
}
