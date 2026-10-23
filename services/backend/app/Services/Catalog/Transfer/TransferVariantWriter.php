<?php

namespace App\Services\Catalog\Transfer;

use App\Actions\Product\AttachProductsToVariableProductAction;
use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;

/** Whole-group transaction: existing SKU IDs, URLs, orders, media and stock stay intact. */
class TransferVariantWriter
{
    public function apply(int $rowId, callable $applySingle): Product
    {
        return DB::transaction(function () use ($rowId, $applySingle): Product {
            $row = DB::table('catalog_transfer_rows')->find($rowId);
            // Serialize group operations even when different rows are dispatched concurrently.
            $file = DB::table('catalog_transfer_files')->where('id', $row->file_id)->lockForUpdate()->first();
            if ($file->status !== 'read') {
                throw new \DomainException('Сначала необходимо полностью прочитать файл.');
            }
            $source = json_decode($row->source ?? '{}', true);
            $group = (string) $source['variant_group'];
            $rows = DB::table('catalog_transfer_rows')->where('file_id', $row->file_id)
                ->where('source->variant_group', $group)->orderBy('row_number')->lockForUpdate()->get();
            if ($rows->count() < 2 || $rows->count() > 200) {
                throw new \DomainException('В группе должно быть от 2 до 200 строк с отдельными кодами 1С.');
            }
            $codes = $rows->pluck('code');
            if ($codes->contains('') || $codes->unique()->count() !== $codes->count()) {
                throw new \DomainException('В группе пустые или повторяющиеся коды 1С.');
            }
            foreach ($rows as $item) {
                if (in_array($item->status, ['error', 'skipped', 'conflict'], true)) {
                    throw new \DomainException('Группа содержит ошибочные строки. Исправьте весь состав группы.');
                }
            }
            foreach (DB::table('catalog_transfer_rows')->where('run_id', $row->run_id)->whereIn('code', $codes)->get() as $other) {
                $membership = json_decode($other->source ?? '{}', true)['variant_group'] ?? null;
                if ((int) $other->file_id !== (int) $row->file_id || (string) $membership !== $group) {
                    throw new \DomainException('Код группы повторён в другом файле или другой группе этого переноса.');
                }
            }

            $products = $rows->map(fn ($item) => $applySingle((int) $item->id))->keyBy('id');
            if ($products->count() !== $rows->count()) {
                throw new \DomainException('Несколько кодов указывают на один товар.');
            }
            if ($products->contains(fn (Product $product) => $product->isVariable() || $product->variants()->exists())) {
                throw new \DomainException('Код 1С указывает на общую карточку с вариантами, а не на отдельное исполнение.');
            }
            $parents = $products->pluck('parent_product_id')->filter()->unique();
            if ($parents->count() > 1) {
                throw new \DomainException('Товары уже относятся к разным родителям. Автоматическое перемещение запрещено.');
            }
            $labels = $products->mapWithKeys(fn (Product $product) => [$product->id => (string) $product->sku])->all();
            if ($parents->isNotEmpty()) {
                $parent = Product::whereKey($parents->first())->lockForUpdate()->firstOrFail();
                if (! $parent->isVariable() || $parent->isVariant()) {
                    throw new \DomainException('Некорректная существующая общая карточка.');
                }
                $parentCodes = $parent->variants()->get(['sku', 'external_id'])->flatMap(fn ($item) => [$item->sku, $item->external_id])->filter()->unique();
                foreach (DB::table('catalog_transfer_rows')->where('run_id', $row->run_id)->whereIn('code', $parentCodes)->get() as $sibling) {
                    $siblingGroup = json_decode($sibling->source ?? '{}', true)['variant_group'] ?? null;
                    if ($siblingGroup !== null && ((int) $sibling->file_id !== (int) $row->file_id || (string) $siblingGroup !== $group)) {
                        throw new \DomainException('Один существующий родитель указан в разных группах таблицы. Требуется ручная проверка.');
                    }
                }
                $unattached = $products->filter(fn (Product $product) => ! $product->isVariant());
            } else {
                // Reuse the same merge action as the admin UI, without moving/deleting SKU records.
                $first = $products->take(2);
                $parent = app(MergeProductsIntoVariableProductAction::class)->execute(
                    new MergeProductsIntoVariableProductData($first->keys()->all(), array_intersect_key($labels, $first->all())),
                    copyMainImage: false,
                );
                $unattached = $products->except($first->keys()->all());
                if (! $products->contains(fn (Product $product) => (string) $product->state === 'active')) {
                    $parent->state = 'draft';
                    $parent->save();
                }
            }
            foreach ($unattached->chunk(20) as $chunk) {
                app(AttachProductsToVariableProductAction::class)->execute($parent, array_intersect_key($labels, $chunk->all()));
            }
            foreach ($rows as $item) {
                $result = json_decode($item->result ?? '{}', true);
                $result['variant_group_applied'] = ['group' => $group, 'file_id' => $row->file_id,
                    'parent_product_id' => $parent->id, 'product_ids' => $products->keys()->all()];
                DB::table('catalog_transfer_rows')->where('id', $item->id)->update([
                    'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
            app(CatalogTransfer::class)->log($row->run_id, 'Группа '.$group.': общая карточка '.$parent->id.', предложений '.$products->count().'.');

            return Product::findOrFail($rows->firstWhere('id', $rowId)->applied_product_id ?? DB::table('catalog_transfer_rows')->find($rowId)->applied_product_id);
        }, 3);
    }
}
