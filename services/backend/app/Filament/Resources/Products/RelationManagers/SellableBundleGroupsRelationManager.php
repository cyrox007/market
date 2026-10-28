<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product\Product;
use App\Services\Product\SellableBundle;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellableBundleGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'sellableBundleGroups';
    protected static ?string $title = 'Группы составляющих комплекта';
    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return ! $ownerRecord->isVariant() && $ownerRecord->isVariable()
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('pivot.sort_order')->label('Порядок'),
            TextColumn::make('name')->label('Группа товара'),
        ])->headerActions([
            Action::make('enableKit')
                ->label(fn (): string => $this->getOwnerRecord()->is_sellable_bundle
                    ? 'Отключить продажу комплектом' : 'Включить продажу комплектом')
                ->requiresConfirmation()
                ->color(fn (): string => $this->getOwnerRecord()->is_sellable_bundle ? 'danger' : 'success')
                ->action(function (): void {
                    $owner = $this->getOwnerRecord();
                    DB::transaction(function () use ($owner): void {
                        $root = Product::lockForUpdate()->findOrFail($owner->id);
                        if ($root->is_sellable_bundle) {
                            $root->forceFill(['is_sellable_bundle' => false])->save();
                        } else {
                            $offers = $root->variants()->active()->get();
                            if ($offers->isEmpty() || ! $root->sellableBundleGroups()->exists()) {
                                throw ValidationException::withMessages(['bundle' => 'Укажите группы и создайте торговые предложения.']);
                            }
                            $root->forceFill(['is_sellable_bundle' => true])->save();
                            foreach ($offers as $offer) {
                                app(SellableBundle::class)->composition($root->id, $offer->id);
                            }
                        }
                        Product::flushAllProductCaches();
                    });
                    Notification::make()->title('Продажа комплектом обновлена')->success()->send();
                }),
            Action::make('addGroup')
                ->label('Добавить группу составляющих')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('component_group_id')
                        ->label('Группа товара: шкаф, антресоль и т. д.')
                        ->searchable()->required()
                        ->getSearchResultsUsing(function (string $search): array {
                            $root = $this->getOwnerRecord();
                            return Product::query()->active()->whereNull('parent_product_id')
                                ->where('id', '!=', $root->id)
                                ->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('sku', 'like', '%'.$search.'%'))
                                ->limit(50)->get()
                                ->filter(fn (Product $group) => app(SellableBundle::class)->groupIsEligible($group, $root))
                                ->mapWithKeys(fn (Product $group) => [$group->id => $group->name])->all();
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->name),
                ])
                ->action(function (array $data): void {
                    $root = $this->getOwnerRecord();
                    $group = Product::findOrFail($data['component_group_id']);
                    if (! app(SellableBundle::class)->groupIsEligible($group, $root)
                        || $root->sellableBundleGroups()->where('products.id', $group->id)->exists()
                        || $root->sellableBundleGroups()->count() >= 29) {
                        throw ValidationException::withMessages(['component_group_id' => 'Недопустимая или уже добавленная группа.']);
                    }
                    DB::transaction(function () use ($root, $group): void {
                        $root->sellableBundleGroups()->attach($group->id, [
                            'sort_order' => (int) $root->sellableBundleGroups()->count(),
                        ]);
                        // Mapping for all offers must be renewed before sale can resume.
                        if ($root->is_sellable_bundle) {
                            $root->forceFill(['is_sellable_bundle' => false])->save();
                        }
                    });
                    Product::flushAllProductCaches();
                    Notification::make()->title('Группа добавлена. Проверьте состав каждого предложения и включите продажу.')->warning()->send();
                }),
        ])->recordActions([
            Action::make('removeGroup')
                ->label('Убрать группу')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (Product $record): void {
                    $root = $this->getOwnerRecord();
                    DB::transaction(function () use ($root, $record): void {
                        $root->sellableBundleGroups()->detach($record->id);
                        if ($root->is_sellable_bundle) {
                            $root->forceFill(['is_sellable_bundle' => false])->save();
                        }
                    });
                    Product::flushAllProductCaches();
                    Notification::make()->title('Группа удалена. Проверьте предложения перед повторным включением.')->warning()->send();
                }),
        ])->defaultSort('sellable_bundle_groups.sort_order');
    }
}
