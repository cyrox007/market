import type { Product, ProductDetail, ProductVariant, ColorOption, SizeOption } from '../lib/api';

/**
 * Проверяет доступность товара
 * @param product - Товар для проверки
 * @returns true если товар доступен (в наличии или под заказ)
 */
export function isProductAvailable(product: Product | ProductDetail): boolean | undefined {
  // Для вариативных товаров проверяем наличие хотя бы одной доступной вариации
  if (product.is_variable && !product.is_variant && 'variants' in product && product.variants) {
    return product.variants.some(v => v.in_stock && (v.stock > 0 || v.backorder));
  }
  
  // Для обычных товаров и вариаций проверяем напрямую
  return product.in_stock && (product.stock > 0 || product.backorder);
}

/**
 * Получает первую доступную вариацию из списка вариаций товара
 * @param product - Вариативный товар с массивом variants
 * @returns Первая доступная вариация или null
 */
export function getFirstAvailableVariant(product: ProductDetail): ProductVariant | null {
  if (!product.variants || product.variants.length === 0) {
    return null;
  }
  
  // Ищем первую доступную вариацию (в наличии или под заказ)
  return product.variants.find(v => v.in_stock && (v.stock > 0 || v.backorder)) || null;
}

/**
 * Находит вариацию по цвету и размеру
 * @param variants - Массив вариаций
 * @param color - Цвет для поиска
 * @param size - Размер для поиска
 * @returns Найденная вариация или null
 */
export function getVariantByColorAndSize(
  variants: ProductVariant[],
  color: ColorOption | null,
  size: SizeOption | null
): ProductVariant | null {
  if (!variants || variants.length === 0) {
    return null;
  }
  
  if (!color && !size) {
    return null;
  }
  
  return variants.find(v => {
    const colorMatch = !color || (
      (v.color?.name === color.name || v.color?.slug === color.slug) ||
      (color.name && v.color?.name === color.name) ||
      (color.slug && v.color?.slug === color.slug)
    );
    
    const sizeMatch = !size || (
      (v.size?.value === size.value || v.size?.slug === size.slug) ||
      (size.value && v.size?.value === size.value) ||
      (size.slug && v.size?.slug === size.slug)
    );
    
    return colorMatch && sizeMatch;
  }) || null;
}

/**
 * Получает статус наличия товара для отображения
 * @param product - Товар для проверки
 * @returns Объект с информацией о наличии
 */
export function getProductStockStatus(product: Product | ProductDetail): {
  available: boolean;
  stock: number;
  backorder: boolean;
  status: 'in_stock' | 'out_of_stock' | 'backorder';
} {
  // Для вариативных товаров проверяем вариации
  if (product.is_variable && !product.is_variant && 'variants' in product && product.variants) {
    const availableVariants = product.variants.filter(v => v.in_stock && (v.stock > 0 || v.backorder));
    const maxStock = availableVariants.length > 0
      ? Math.max(...availableVariants.map((v) => Math.max(0, Math.floor(Number(v.stock) || 0))))
      : 0;
    const hasBackorder = availableVariants.some(v => v.backorder);

    return {
      available: availableVariants.length > 0,
      stock: maxStock,
      backorder: hasBackorder,
      status: hasBackorder ? 'backorder' : (maxStock > 0 ? 'in_stock' : 'out_of_stock'),
    };
  }
  
  // Для обычных товаров
  const available = (product.in_stock ?? false) && (product.stock > 0 || product.backorder);
  return {
    available: available ?? false,
    stock: product.stock || 0,
    backorder: product.backorder || false,
    status: product.backorder ? 'backorder' : (product.stock > 0 ? 'in_stock' : 'out_of_stock'),
  };
}

/**
 * Форматирует остаток товара в категорию
 * @param stock - Количество товара на складе
 * @param settings - Настройки категорий (опционально, если не переданы - используем значения по умолчанию)
 * @returns Категория остатка: "мало", "средне", "много" или точное число
 */
export type StockSettings = {
  stock_low_max?: number;
  stock_medium_max?: number;
  stock_high_max?: number;
  show_exact_above?: number;
};

export function formatStockCategory(stock: number, settings?: StockSettings): string {
  return formatStockDisplayText(stock, settings);
}

/** Текст остатка для бейджа по настройкам /api/stock-settings (как в админке). */
export function formatStockDisplayText(stock: number, settings?: StockSettings): string {
  const normalizedStock = Math.max(0, Math.floor(Number(stock) || 0));
  const lowMax = settings?.stock_low_max ?? 1;
  const mediumMax = settings?.stock_medium_max ?? 5;
  const highMax = settings?.stock_high_max ?? 10;
  const showExactAbove = settings?.show_exact_above ?? 0;

  if (normalizedStock < lowMax) return 'мало';
  if (normalizedStock <= mediumMax) return 'средне';
  if (normalizedStock <= highMax) return 'много';

  if (showExactAbove > 0 && normalizedStock > showExactAbove) {
    return `${normalizedStock} шт.`;
  }

  return 'много';
}

export function resolveProductStockBadge(params: {
  product: Product | ProductDetail;
  selectedVariant?: ProductVariant | null;
  stockSettings?: StockSettings;
}): {
  stock: number;
  inStock: boolean;
  backorder: boolean;
  stockText: string;
  badgeLabel: string;
  badgeClass: string;
} {
  const { product, selectedVariant, stockSettings } = params;

  let stock = Math.max(0, Math.floor(Number(product.stock) || 0));
  let inStock = Boolean(product.in_stock);
  let backorder = Boolean(product.backorder);

  if (product.is_variable && !product.is_variant && 'variants' in product && product.variants?.length) {
    if (selectedVariant) {
      stock = Math.max(0, Math.floor(Number(selectedVariant.stock) || 0));
      inStock = Boolean(selectedVariant.in_stock);
      backorder = Boolean(selectedVariant.backorder);
    } else {
      const status = getProductStockStatus(product);
      stock = Math.max(0, Math.floor(Number(status.stock) || 0));
      inStock = status.available;
      backorder = status.backorder;
    }
  }

  const stockText = formatStockDisplayText(stock, stockSettings);

  let badgeLabel: string;
  let badgeClass: string;

  if (backorder && stock === 0) {
    badgeLabel = 'Под заказ';
    badgeClass = 'bg-yellow-100 text-yellow-700';
  } else if (!inStock) {
    badgeLabel = 'Нет в наличии';
    badgeClass = 'bg-red-100 text-red-700';
  } else if (stock > 0) {
    badgeLabel = `В наличии (${stockText})`;
    badgeClass = 'bg-green-100 text-green-700';
  } else {
    badgeLabel = 'Товар недоступен для заказа';
    badgeClass = 'bg-yellow-100 text-yellow-700';
  }

  return { stock, inStock, backorder, stockText, badgeLabel, badgeClass };
}
