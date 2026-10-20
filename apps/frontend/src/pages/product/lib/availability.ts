/** Наличие варианта (или простого товара) для региона покупателя — слова из GLOSSARY.md */

export type AvailabilityStatus = 'in_stock' | 'backorder' | 'out';

export interface StockFields {
  in_stock?: boolean | null;
  stock?: number | null;
  /** false — магазин не знает остаток: число не показываем */
  stock_known?: boolean | null;
  available_to_order?: boolean | null;
  /** У варианта в типах строка, бэк варианту поле не отдаёт — считаем только true */
  backorder?: boolean | string | null;
}

export interface Availability {
  status: AvailabilityStatus;
  /** Остаток для плашки «Остаток в …» — только если известен */
  stock: number | null;
  /** Сколько можно положить в корзину; undefined — без предела */
  maxQuantity: number | undefined;
}

export function availabilityOf(item: StockFields): Availability {
  const stock = Math.max(0, Math.floor(Number(item.stock) || 0));
  const known = item.stock_known !== false && stock > 0;

  if (item.in_stock === true) {
    return {
      status: 'in_stock',
      stock: known ? stock : null,
      maxQuantity: known ? stock : undefined,
    };
  }
  if (item.available_to_order === true || item.backorder === true) {
    return { status: 'backorder', stock: null, maxQuantity: undefined };
  }
  return { status: 'out', stock: null, maxQuantity: 0 };
}
