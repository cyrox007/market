<?php

namespace App\Filament\Resources\InteriorIdeas\RelationManagers;

use App\Models\Product\Product;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class HotspotsRelationManager extends RelationManager
{
    protected static string $relationship = 'hotspots';

    protected static ?string $title = 'Точки на изображении';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        $ownerRecord = $this->getOwnerRecord();
        $imageUrl = $ownerRecord->getFirstMediaUrl('image', 'main') ?: $ownerRecord->getFirstMediaUrl('image');

        return $schema
            ->components([
                Section::make('Визуализация')
                    ->description('Изображение интерьера с существующими точками. Введите координаты X и Y в полях формы ниже.')
                    ->schema([
                        Placeholder::make('image_preview')
                            ->label('')
                            ->content(function () use ($ownerRecord, $imageUrl) {
                                if (!$imageUrl) {
                                    return new HtmlString('<p class="text-gray-500">Загрузите изображение интерьера в основной форме, чтобы увидеть визуализацию.</p>');
                                }
                                
                                // Загружаем hotspots с продуктами
                                $hotspots = $ownerRecord->hotspots()->with('product')->get();
                                
                                // Формируем данные для отображения всех точек
                                // При редактировании текущая точка тоже будет показана (это нормально)
                                $hotspotsData = $hotspots->map(function ($h) {
                                    return [
                                        'id' => $h->id,
                                        'x' => (float)$h->x,
                                        'y' => (float)$h->y,
                                        'product' => $h->product ? $h->product->name : 'Товар'
                                    ];
                                })->values()->all();
                                
                                return new HtmlString(view('filament.resources.interior-ideas.hotspot-visualizer', [
                                    'imageUrl' => $imageUrl,
                                    'hotspots' => $hotspotsData,
                                ])->render());
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Параметры точки')
                    ->schema([
                        Select::make('product_id')
                            ->label('Товар')
                            ->relationship('product', 'name', modifyQueryUsing: function ($query) {
                                // Ограничиваем загрузку - загружаем только при поиске
                                return $query;
                            })
                            ->searchable(['name', 'id'])
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => "{$record->name} (ID: {$record->id})")
                            ->required()
                            ->helperText('Начните вводить название товара или ID для поиска')
                            ->columnSpanFull(),

                        TextInput::make('x')
                            ->label('Координата X (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->default(50)
                            ->helperText('Позиция точки по горизонтали в процентах (0-100)')
                            ->suffix('%'),

                        TextInput::make('y')
                            ->label('Координата Y (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->default(50)
                            ->helperText('Позиция точки по вертикали в процентах (0-100)')
                            ->suffix('%'),

                        TextInput::make('priority')
                            ->label('Приоритет')
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше точка в списке'),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('x')
                    ->label('X (%)')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2) . '%'),

                TextColumn::make('y')
                    ->label('Y (%)')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2) . '%'),

                TextColumn::make('priority')
                    ->label('Приоритет')
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
}
