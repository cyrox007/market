<?php

namespace App\Actions\Product;

use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Vanilo\Product\Models\ProductState;

/**
 * Отвязывает торговое предложение от общей карточки: товар снова становится самостоятельным.
 */
class DetachVariantFromParentAction
{
    /**
     * Причина, по которой отвязка недоступна, или null.
     */
    public static function blockReason(Product $variant): ?string
    {
        if (! $variant->isVariant()) {
            return 'Товар не является торговым предложением.';
        }

        $parentExternalId = trim((string) ($variant->parentProduct?->external_id ?? ''));
        if ($parentExternalId !== '') {
            return 'Группа загружена из 1С (у общей карточки есть ID 1С): при следующей синхронизации отвязанный товар будет создан заново.';
        }

        return null;
    }

    public function execute(Product $variant): Product
    {
        $reason = self::blockReason($variant);
        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }

        /** @var Product $parent */
        $parent = $variant->parentProduct;

        DB::transaction(function () use ($variant, $parent): void {
            DB::table('product_variant_attributes')->where('product_id', $variant->id)->delete();

            $variant->updateQuietly([
                'parent_product_id' => null,
                'is_variable' => false,
            ]);

            // Пустая карточка остаётся вариативной для повторной привязки и скрывается с витрины
            if (! $parent->variants()->exists()) {
                $parent->updateQuietly(['state' => ProductState::INACTIVE]);
            } else {
                Product::syncParentPriceFromVariants($parent);
            }
        });

        $parent->fresh()->flushCache();
        $variant->fresh()->flushCache();
        Product::flushAllProductCaches();

        Log::info('[DetachVariantFromParent] detached', [
            'variant_id' => $variant->id,
            'parent_id' => $parent->id,
            'parent_emptied' => ! $parent->variants()->exists(),
        ]);

        return $variant->fresh();
    }
}
