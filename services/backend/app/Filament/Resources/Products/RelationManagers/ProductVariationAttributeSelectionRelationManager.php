<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductVariationAttributeSelectionRelationManager extends RelationManager
{
    protected static string $relationship = 'variationAttributeSelection';

    protected static ?string $title = 'Атрибуты вариаций для этого товара';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord->isVariant()) {
            return false;
        }
        if (!$ownerRecord->is_variable) {
            return false;
        }
        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Атрибут'),
                TextColumn::make('slug')->label('Slug'),
            ])
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->recordSelectOptionsQuery(fn(Builder $query) => Attribute::variationAttributes()->orderBy('sort_order'))
                    ->recordTitle(fn(Attribute $record) => $record->name . ' (' . $record->slug . ')')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'slug'])
                    ->form(function (\Filament\Actions\AttachAction $action) {
                        $product = $this->getOwnerRecord();
                        $taxon = $product->category();
                        $defaultIds = [];
                        
                        if ($taxon) {
                            $category = $taxon instanceof \App\Models\Product\Category
                                ? $taxon
                                : \App\Models\Product\Category::find($taxon->id);
                            if ($category) {
                                if (!$category->relationLoaded('variationAttributes')) {
                                    $category->load('variationAttributes');
                                }
                                $defaultIds = $category->variationAttributes->pluck('id')->toArray();
                            }
                        }
                        
                        return [
                            $action->getRecordSelect()
                                ->multiple()
                                ->default($defaultIds)
                                ->helperText($defaultIds ? 'Предвыбраны атрибуты из категории товара. Вы можете добавить свои или убрать предвыбранные.' : 'Выберите атрибуты вариаций для этого товара.'),
                        ];
                    }),
            ])
            ->actions([
                \Filament\Actions\DetachAction::make(),
            ])
            ->emptyStateHeading('Не выбрано атрибутов вариаций')
            ->emptyStateDescription('Добавьте атрибуты (Цвет, Размер, Комплект и т.д.), которые будут использоваться в торговых предложениях этого товара. При добавлении будут автоматически предвыбраны атрибуты из категории товара (если они заданы). Если не добавить ни одного — будут использоваться все глобальные атрибуты с флагом «Участвует в торговых предложениях».');
    }
}
