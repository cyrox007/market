<?php

namespace App\Filament\Resources\About\RelationManagers;

use App\Models\Page\Advantage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdvantagesRelationManager extends RelationManager
{
    protected static string $relationship = 'advantages';

    protected static ?string $title = null;

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/admin_sv/advantages_relation_manager.основнаяинформация'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament/admin_sv/advantages_relation_manager.title'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('filament/admin_sv/advantages_relation_manager.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('icon')
                            ->label(__('filament/admin_sv/advantages_relation_manager.icon'))
                            ->options([
                                'ri-star-line' => 'Звезда',
                                'ri-price-tag-3-line' => 'Ценник',
                                'ri-leaf-line' => 'Лист',
                                'ri-shield-check-line' => 'Щит',
                                'ri-heart-line' => 'Сердце',
                                'ri-trophy-line' => 'Трофей',
                                'ri-award-line' => 'Награда',
                                'ri-customer-service-2-line' => 'Сервис',
                                'ri-truck-line' => 'Доставка',
                                'ri-time-line' => 'Время',
                                'ri-user-smile-line' => 'Улыбка',
                                'ri-gift-line' => 'Подарок',
                            ])
                            ->searchable()
                            ->allowHtml(false)
                            ->helperText('Выберите иконку из RemixIcon'),
                        Select::make('color')
                            ->label(__('filament/admin_sv/advantages_relation_manager.color'))
                            ->options([
                                'red' => 'Красный',
                                'yellow' => 'Желтый',
                                'green' => 'Зеленый',
                                'blue' => 'Синий',
                                'purple' => 'Фиолетовый',
                                'pink' => 'Розовый',
                            ])
                            ->default('red')
                            ->helperText('Цвет для оформления карточки преимущества'),
                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/advantages_relation_manager.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament/admin_sv/advantages_relation_manager.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('filament/admin_sv/advantages_relation_manager.description'))
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('icon')
                    ->label(__('filament/admin_sv/advantages_relation_manager.icon'))
                    ->searchable(),
                TextColumn::make('color')
                    ->label(__('filament/admin_sv/advantages_relation_manager.color'))
                    ->badge(),
                TextColumn::make('priority')
                    ->label(__('filament/admin_sv/advantages_relation_manager.priority'))
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('priority');
    }
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/admin_sv/advantages_relation_manager.title');
    }

}

