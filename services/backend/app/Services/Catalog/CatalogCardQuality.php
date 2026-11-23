<?php

namespace App\Services\Catalog;

use App\Models\Product\Product;
use App\Services\Product\ProductDescriptionHtml;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CatalogCardQuality
{
    /** @return list<array{code:string,severity:string,label:string}> */
    public function inspect(Product $product, array $duplicateExternalIds = [], bool $verifyFiles = true): array
    {
        if ($product->getRawOriginal('state') !== Product::ACTIVE) {
            return [];
        }

        $issues = [];
        $parent = $product->isVariant() ? $product->parentProduct : null;
        // A variant with an inactive/missing parent is not a publicly sellable SKU.
        if ($product->isVariant() && (! $parent || $parent->getRawOriginal('state') !== Product::ACTIVE)) {
            return [];
        }
        $variableParent = $product->isVariable() && ! $product->isVariant();
        $activeOffers = $variableParent
            ? $product->variants->filter(fn (Product $offer) => $offer->getRawOriginal('state') === Product::ACTIVE)
            : collect();

        $add = static function (string $code, string $severity, string $label) use (&$issues): void {
            $issues[] = compact('code', 'severity', 'label');
        };

        if ($variableParent && $activeOffers->isEmpty()) {
            $add('no_active_offers', 'critical', 'У вариативного товара нет активных предложений');
        }

        // Virtual parents have a display-only price synchronized from their active SKUs.
        // An individual sellable SKU (including bundle offers) must have a positive base price.
        if (! $variableParent && (float) ($product->getRawOriginal('price') ?? 0) <= 0) {
            $add('no_price', 'critical', 'Отсутствует положительная цена');
        }

        $mainImage = $this->effectiveMainImage($product, $parent, $activeOffers);
        if ($mainImage === null) {
            $add('no_image', 'critical', 'Нет главного изображения с учётом наследования');
        } elseif ($verifyFiles) {
            // File I/O is only performed by the scheduled command, never when rendering Filament.
            if (! Storage::disk($mainImage->disk)->exists($mainImage->getPathRelativeToRoot())) {
                $add('no_image', 'critical', 'Оригинал главного изображения отсутствует на диске');
            } elseif ($mainImage->created_at?->lte(now()->subMinutes(30))
                && (! $mainImage->hasGeneratedConversion('thumb')
                    || ! Storage::disk($mainImage->conversions_disk ?: $mainImage->disk)
                        ->exists($mainImage->getPathRelativeToRoot('thumb')))) {
                $add('missing_thumb', 'warning', 'Оригинал изображения есть, миниатюра не сформирована');
            }
        }

        if ($product->taxons->isEmpty() && (! $parent || $parent->taxons->isEmpty())) {
            $add('no_category', 'warning', 'Не назначена категория, включая категорию родителя');
        }

        // The parent of a variable product is not a stock-keeping saleable SKU.
        if (! $variableParent) {
            $externalId = trim((string) ($product->getRawOriginal('external_id') ?? ''));
            if ($externalId === '') {
                $add('no_external_id', 'warning', 'У продаваемой позиции нет external_id');
            } elseif (isset($duplicateExternalIds[$externalId])) {
                $add('duplicate_external_id', 'warning', 'external_id повторяется у продаваемых позиций');
            }
        }

        if (! app(ProductDescriptionHtml::class)->hasContent($product->getEffectiveDescription())) {
            $add('no_description', 'warning', 'Нет описания ни в карточке, ни у родителя');
        }

        if ($product->colorRows->contains(fn ($row) => $row->color_solution_id === null
            || ! $row->solution)) {
            $add('incomplete_colors', 'warning', 'Есть строка цвета без выбранного цветового решения');
        }

        return $issues;
    }

    /** @param \Illuminate\Support\Collection<int, Product> $activeOffers */
    private function effectiveMainImage(Product $product, ?Product $parent, $activeOffers): ?Media
    {
        $own = $product->getFirstMedia('images');
        if ($own) {
            return $own;
        }

        if ($parent) {
            return $parent->getFirstMedia('images');
        }

        if ($product->isVariable()) {
            foreach ($activeOffers->sortBy('id') as $offer) {
                if ($image = $offer->getFirstMedia('images')) {
                    return $image;
                }
            }
        }

        return null;
    }

    public static function typeLabel(Product $product): string
    {
        $kind = $product->isVariant() ? 'Торговое предложение'
            : ($product->isVariable() ? 'Родитель вариаций' : 'Самостоятельный товар');

        return $product->is_sellable_bundle ? 'Комплект · '.$kind : $kind;
    }
}
