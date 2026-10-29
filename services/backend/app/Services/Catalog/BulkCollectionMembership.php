<?php

namespace App\Services\Catalog;

use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operator-controlled membership of a furniture series.
 * Never guesses by a manufacturer's name or a substring in a product title.
 */
class BulkCollectionMembership
{
    /**
     * @param array<int,int|string> $productIds selected parent catalogue cards
     * @return array{collection_id:int, selected:int, added:int, removed:int, recommendations_enabled:bool}
     */
    public function apply(int $collectionId, array $productIds, string $operation, bool $enableRecommendations = false): array
    {
        if (! in_array($operation, ['add', 'remove'], true)) {
            throw ValidationException::withMessages(['operation' => 'Выберите добавление или удаление из серии.']);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), fn (int $id): bool => $id > 0)));
        if ($collectionId <= 0 || $ids === [] || count($ids) > 100) {
            throw ValidationException::withMessages(['collection_id' => 'Выберите серию и от 1 до 100 товаров.']);
        }

        $cards = Product::query()->whereIn('id', $ids)->get(['id', 'parent_product_id'])->keyBy('id');
        foreach ($ids as $id) {
            if (! $cards->has($id) || $cards[$id]->parent_product_id !== null) {
                throw ValidationException::withMessages(['collection_id' =>
                    "Позиция #{$id} отсутствует либо является цветовым предложением. Выбирайте родительские карточки."]);
            }
        }

        $result = DB::transaction(function () use ($collectionId, $ids, $operation, $enableRecommendations): array {
            $collection = ProductCollection::query()->lockForUpdate()->find($collectionId);
            if (! $collection || $collection->is_auto || filled($collection->scope_type)) {
                throw ValidationException::withMessages(['collection_id' =>
                    'Выберите существующую ручную подборку серии, а не системную автоматическую подборку.']);
            }

            $existing = $collection->products()->whereIn('products.id', $ids)
                ->pluck('products.id')->map(fn ($id) => (int) $id)->all();

            $added = 0;
            $removed = 0;
            if ($operation === 'add') {
                $newIds = array_values(array_diff($ids, $existing));
                if ($newIds !== []) {
                    $maxOrder = (int) ($collection->products()->max('product_product_collection.sort_order') ?? 0);
                    $positions = [];
                    foreach ($newIds as $id) {
                        $positions[$id] = ['sort_order' => ++$maxOrder];
                    }
                    $collection->products()->syncWithoutDetaching($positions);
                    $added = count($newIds);
                }
                if ($enableRecommendations && ! $collection->recommend_collection_members) {
                    $collection->update(['recommend_collection_members' => true]);
                }
            } else {
                if ($existing !== []) {
                    $collection->products()->detach($existing);
                    $removed = count($existing);
                }
            }
            return [
                'collection_id' => (int) $collection->id,
                'selected' => count($ids),
                'added' => $added,
                'removed' => $removed,
                'recommendations_enabled' => (bool) $collection->recommend_collection_members,
            ];
        }, 3);

        if ($result['added'] || $result['removed']) {
            ProductCollection::findOrFail($collectionId)->flushHomeCaches();
        }
        return $result;
    }
}
