import { FileText, ListChecks, Star } from 'lucide-react';
import VariantAttributeSelector from '../../../components/product/VariantAttributeSelector';
import VariantColorSelector from '../../../components/product/VariantColorSelector';
import type { ProductDetail, ProductVariant } from '../../../lib/api';

interface Props {
  product: ProductDetail;
  selectedVariant: ProductVariant | null;
  selection: Record<string, string>;
  isValueAvailable: (attributeSlug: string, valueSlug: string) => boolean;
  onSelectValue: (attributeSlug: string, valueSlug: string, isDisabledClick?: boolean) => void;
  onPickVariant: (variantId: number) => void;
  /** Пока перезагружается товар (смена региона) — выбор недоступен */
  disabled: boolean;
  hasDescription: boolean;
  onGoToDetails: () => void;
}

/** Наличие, артикул, название, рейтинг, выбор варианта */
export default function ProductSummary({
  product,
  selectedVariant,
  selection,
  isValueAvailable,
  onSelectValue,
  onPickVariant,
  disabled,
  hasDescription,
  onGoToDetails,
}: Props) {
  // Остатки ближайшего склада бэк отдаёт только у товара; у варианта — его остаток
  const stock = selectedVariant
    ? (selectedVariant.stock ?? 0)
    : (product.nearest_stock ?? product.stock ?? 0);
  const inStock = stock > 0 || product.backorder;
  const sku = selectedVariant?.sku || product.sku;

  return (
    <div className="flex-1 space-y-4 sm:space-y-6 order-3 lg:order-3">
      <div>
        <div className="flex items-center gap-2 mb-3 flex-wrap">
          {inStock ? (
            <span className="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 whitespace-nowrap">
              В наличии
            </span>
          ) : (
            <span className="px-3 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-600 whitespace-nowrap">
              Нет в наличии
            </span>
          )}
          {inStock && stock > 0 && (
            <span className="text-xs text-gray-600 whitespace-nowrap">
              {stock} шт.
              {product.nearest_warehouse_name && (
                <span className="text-xs text-gray-500 ml-1">
                  (склад «{product.nearest_warehouse_name}»)
                </span>
              )}
            </span>
          )}
          {sku && <span className="text-xs text-gray-500">Арт: {sku}</span>}
        </div>
        <h1 className="text-xl sm:text-2xl font-bold mb-3">{product.name}</h1>
        <div className="flex items-center gap-1">
          {[...Array(5)].map((_, i) => (
            <Star
              className="size-[1em] text-yellow-500 text-base sm:text-lg"
              fill={i < Math.floor(product.rating || 0) ? 'currentColor' : 'none'}
              key={i}
            />
          ))}
          <span className="ml-2 text-gray-900 font-medium text-sm sm:text-base">
            {product.rating ?? 0}
          </span>
        </div>
      </div>

      {product.variation_attributes
        ?.filter((a) => a.attribute_slug !== 'color' && a.values?.length)
        .map((attr) => (
          <VariantAttributeSelector
            key={attr.attribute_slug}
            attribute={attr}
            selectedValues={
              selectedVariant && selection[attr.attribute_slug]
                ? new Set([selection[attr.attribute_slug]])
                : undefined
            }
            isValueAvailable={(valueSlug) => isValueAvailable(attr.attribute_slug, valueSlug)}
            onSelect={(valueSlug, isDisabledClick) =>
              onSelectValue(attr.attribute_slug, valueSlug, isDisabledClick)
            }
            isDisabled={disabled}
          />
        ))}

      {product.variants && product.variants.length > 0 && (
        <VariantColorSelector
          variants={product.variants}
          selectedVariantId={selectedVariant?.id ?? null}
          onSelect={onPickVariant}
        />
      )}

      <button
        onClick={onGoToDetails}
        className="w-full border-2 border-red-600 text-red-600 py-3 rounded-lg font-medium hover:bg-red-50 transition-colors whitespace-nowrap flex items-center justify-center gap-2"
      >
        {hasDescription ? (
          <FileText className="size-[1em] text-lg" />
        ) : (
          <ListChecks className="size-[1em] text-lg" />
        )}
        {hasDescription ? 'Перейти к описанию' : 'Перейти к характеристикам'}
      </button>
    </div>
  );
}
