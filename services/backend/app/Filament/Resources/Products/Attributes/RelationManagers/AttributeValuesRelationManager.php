<?php

namespace App\Filament\Resources\Products\Attributes\RelationManagers;

use App\Models\Product\AttributeValue;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Значения характеристики';

    protected static ?string $recordTitleAttribute = 'value';

    public function form(Schema $schema): Schema
    {
        $attribute = $this->getOwnerRecord();
        $type = $attribute->type ?? 'select';
        $isColor = $type === 'color';
        $isString = $type === 'string';

        $valueLabel = $isColor ? 'Название цвета' : 'Значение';
        $valueHelper = match ($type) {
            'color' => 'Название для отображения (например: Серый, Белый). Ниже укажите HEX для чипа на сайте.',
            'string' => 'Вариант выбора в вариациях (например: «Комплект 120», «С подлокотником»). На сайте пользователь выбирает из этого списка.',
            default => 'Название значения (например: Большой, ЛДСП)',
        };

        $components = [
            TextInput::make('value')
                ->label($valueLabel)
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set) {  
                    if (!$state) {
                        return;
                    }
                    $set('slug', \Str::slug($state));
                })
                ->helperText($valueHelper),

            TextInput::make('slug')
                ->label('Slug')
                //->required()
                ->unique(ignoreRecord: true, table: 'product_attribute_values', column: 'slug')
                ->maxLength(255)
                ->helperText('Уникальный идентификатор для API и URL. Оставьте пустым для автоматической генерации.'),

            TextInput::make('sort_order')
                ->label('Порядок сортировки')
                ->numeric()
                ->default(0)
                ->helperText('Чем меньше число, тем выше в списке'),
        ];

        if ($isColor) {
            $components[] = ColorPicker::make('color_code')
                ->label('HEX цвета')
                ->helperText('Цвет чипа на карточке товара');
        }

        return $schema->components($components);
    }

    public function table(Table $table): Table
    {
        $attribute = $this->getOwnerRecord();
        $isColor = $attribute->type === 'color';

        return $table
            ->columns([
                TextColumn::make('value')
                    ->label('Значение')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('color_code')
                    ->label('Цвет')
                    ->visible($isColor)
                    ->badge()
                    ->color(fn ($state) => $state ?: 'gray')
                    ->formatStateUsing(fn ($state) => $state ?: '—'),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['attribute_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }
}

