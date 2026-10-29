<?php

namespace App\Services\Product;

use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mass-edit editorial (not sellable bundle) product recommendations.
 *
 * Operates on ROOT catalogue cards only. Directed database pivot rows are
 * maintained as symmetric pairs; no product name / collection guessing.
 */
class BulkRelatedProducts
{
    /**
     * @param array<int, int|string> $sourceIds Selected cards in product manager.
     * @param array<int, int|string> $targetIds Manually chosen recommended cards.
     * @return array{sources: int, targets: int, added: int, removed: int}
     */
    public function apply(array $sourceIds, array $targetIds, string $mode): array
    {
        if (! in_array($mode, ['add', 'remove', 'replace'], true)) {
            throw ValidationException::withMessages(['mode' => 'Выберите действие: добавить, удалить или заменить.']);
        }

        $sources = $this->ids($sourceIds);
        $targets = $this->ids($targetIds);

        if (! $sources || ! $targets) {
            throw ValidationException::withMessages(['target_product_ids' => 'Выберите исходные карточки и рекомендуемые товары.']);
        }
        if (count($sources) > 100 || count($targets) > 100 || count($sources) * count($targets) > 5000) {
            throw ValidationException::withMessages(['target_product_ids' => 'Для одной операции допускается до 100 исходных и 100 целевых товаров, не более 5000 связей.']);
        }

        $allIds = array_values(array_unique([...$sources, ...$targets]));
        $cards = Product::query()->whereIn('id', $allIds)
            ->select(['id', 'parent_product_id', 'state'])->get()->keyBy('id');

        foreach ($allIds as $id) {
            if (! $cards->has($id) || $cards[$id]->parent_product_id !== null) {
                throw ValidationException::withMessages(['target_product_ids' => "Товар #{$id} отсутствует или является торговым предложением. Выбирайте родительские карточки."]);
            }
        }
        if ($mode !== 'remove') {
            foreach ($targets as $id) {
                if ($cards[$id]->getRawOriginal('state') !== Product::ACTIVE) {
                    throw ValidationException::withMessages(['target_product_ids' => "Товар #{$id} неактивен. Нельзя рекомендовать скрытую карточку."]);
                }
            }
        }

        // Unique unordered edges. A->B and B->A are a SINGLE user-visible link.
        // In replace mode, an edge between two selected sources survives when
        // *either* source explicitly requested it, keeping the graph symmetric.
        $desired = [];
        foreach ($sources as $source) {
            foreach ($targets as $target) {
                if ($source !== $target) {
                    $desired[$this->key($source, $target)] = [$source, $target];
                }
            }
        }
        if (! $desired) {
            throw ValidationException::withMessages(['target_product_ids' => 'Нельзя рекомендовать карточку самой себе. Выберите другие товары.']);
        }

        $summary = DB::transaction(function () use ($sources, $targets, $mode, $desired): array {
            // One read per selected source rather than a query for each link.
            $current = DB::table('product_related_products')
                ->whereIn('product_id', $sources)
                ->lockForUpdate()
                ->get(['product_id', 'related_product_id']);
            $existing = [];
            foreach ($current as $link) {
                $a = (int) $link->product_id;
                $b = (int) $link->related_product_id;
                if ($a !== $b) {
                    $existing[$this->key($a, $b)] = [$a, $b];
                }
            }

            $remove = $mode === 'replace'
                ? array_diff_key($existing, $desired)
                : ($mode === 'remove' ? array_intersect_key($desired, $existing) : []);
            $add = $mode === 'remove' ? [] : $desired;

            if ($remove) {
                // Delete both sides together, including old reverse-only links.
                $deletions = [];
                foreach ($remove as [$a, $b]) {
                    $deletions[$a][] = $b;
                    $deletions[$b][] = $a;
                }
                foreach ($deletions as $id => $otherIds) {
                    DB::table('product_related_products')->where('product_id', $id)
                        ->whereIn('related_product_id', array_unique($otherIds))->delete();
                }
            }

            $added = 0;
            if ($add) {
                $nodes = [];
                foreach ($add as [$a, $b]) {
                    $nodes[$a] = $a;
                    $nodes[$b] = $b;
                }
                $nodeIds = array_values($nodes);
                $links = DB::table('product_related_products')
                    ->whereIn('product_id', $nodeIds)
                    ->whereIn('related_product_id', $nodeIds)
                    ->get(['product_id', 'related_product_id']);
                $already = [];
                foreach ($links as $link) {
                    $already[(int) $link->product_id . ':' . (int) $link->related_product_id] = true;
                }

                $maxOrder = DB::table('product_related_products')
                    ->whereIn('product_id', $nodeIds)
                    ->groupBy('product_id')
                    ->selectRaw('product_id, MAX(sort_order) as max_order')
                    ->pluck('max_order', 'product_id')->map(fn ($v) => (int) $v)->all();

                $inserts = [];
                $now = now();
                foreach ($add as [$a, $b]) {
                    $madePair = false;
                    foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
                        if (isset($already[$from . ':' . $to])) {
                            continue;
                        }
                        $maxOrder[$from] = ($maxOrder[$from] ?? 0) + 1;
                        $inserts[] = [
                            'product_id' => $from, 'related_product_id' => $to,
                            'sort_order' => $maxOrder[$from],
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                        $already[$from . ':' . $to] = true;
                        $madePair = true;
                    }
                    if ($madePair) {
                        $added++;
                    }
                }
                foreach (array_chunk($inserts, 250) as $chunk) {
                    DB::table('product_related_products')->insertOrIgnore($chunk);
                }
            }

            return [
                'sources' => count($sources), 'targets' => count($targets),
                'added' => $added, 'removed' => count($remove),
            ];
        }, 3);

        if ($summary['added'] || $summary['removed']) {
            Product::flushAllProductCaches();
        }

        return $summary;
    }

    /** @param array<int, int|string> $ids */
    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids), fn (int $id): bool => $id > 0
        )));
    }

    private function key(int $a, int $b): string
    {
        return min($a, $b) . ':' . max($a, $b);
    }
}
