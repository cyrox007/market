import useSWR from 'swr';
import { Calendar, Minus, Plus } from 'lucide-react';
import { api } from '../../../lib/api';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { resolveProductStockBadge } from '../../../utils/productUtils';
import { formatPrice } from '../lib/productText';
import type { useProductCartActions } from '../hooks/useProductCartActions';

const DEFAULT_STOCK_SETTINGS = {
  stock_low_max: 1,
  stock_medium_max: 5,
  stock_high_max: 10,
  show_exact_above: 0,
};

interface Props {
  product: ProductDetail;
  selectedVariant: ProductVariant | null;
  sku: string | undefined;
  isAvailable: boolean;
  cart: ReturnType<typeof useProductCartActions>;
}

/** Правая колонка: наличие, цена, корзина */
export default function BuyPanel({ product, selectedVariant, sku, isAvailable, cart }: Props) {
  const { data } = useSWR('/api/stock-settings', () => api.stockSettings.get(), {
    revalidateOnFocus: false,
    dedupingInterval: 60_000,
  });
  const stockBadge = resolveProductStockBadge({
    product,
    selectedVariant,
    stockSettings: { ...(data?.stock_settings ?? DEFAULT_STOCK_SETTINGS), show_exact_above: 0 },
  });
  const price = selectedVariant?.price ?? product.price;
  const oldPrice = selectedVariant?.old_price ?? product.old_price;

  return (
    <div className="bg-gray-50 rounded-2xl p-4 sm:p-6 h-fit lg:sticky lg:top-20 order-3 lg:order-3">
      <div className="mb-4">
        <div className="flex items-center gap-2 justify-center flex-wrap">
          <span
            className={`px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap ${stockBadge.badgeClass}`}
          >
            {stockBadge.badgeLabel}
          </span>
          {sku && <span className="text-xs text-gray-500">Арт: {sku}</span>}
        </div>
      </div>

      <div className="mb-6 text-center">
        <div className="flex items-center justify-center gap-2 mb-2 flex-wrap">
          <span className="text-2xl sm:text-3xl font-bold text-red-600">
            {formatPrice(price ?? 0)}
          </span>
          {oldPrice && price && oldPrice > price && (
            <span className="bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap">
              -{Math.round((1 - price / oldPrice) * 100)}%
            </span>
          )}
        </div>
        {oldPrice && (
          <span className="text-sm text-gray-400 line-through">{formatPrice(oldPrice)}</span>
        )}
      </div>

      <div className="space-y-3">
        {cart.error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {cart.error}
          </div>
        )}
        <div className="flex gap-2">
          {cart.cartQuantity > 0 ? (
            <>
              <button
                disabled
                className="py-3 rounded-lg font-semibold transition-all whitespace-nowrap shadow-lg bg-green-600 text-white hover:bg-green-700 shadow-green-600/30 flex-shrink-0 disabled:opacity-100"
                style={{ width: '120px' }}
              >
                Добавлено
              </button>
              <div className="flex items-center gap-3 flex-1 rounded-lg px-4 animate-[fadeIn_0.3s_ease-in-out]">
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    void cart.decrement();
                  }}
                  disabled={cart.isBusy}
                  className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50"
                >
                  <Minus className="size-[1em]" />
                </button>
                <span className="text-lg font-bold flex-1 text-center">{cart.cartQuantity}</span>
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    void cart.increment();
                  }}
                  disabled={cart.isBusy || !isAvailable}
                  className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50"
                >
                  <Plus className="size-[1em]" />
                </button>
              </div>
            </>
          ) : (
            <button
              onClick={() => void cart.add()}
              disabled={cart.isBusy || !isAvailable}
              className={`py-3 rounded-lg font-semibold transition-all whitespace-nowrap shadow-lg disabled:opacity-50 disabled:cursor-not-allowed ${
                !isAvailable
                  ? 'bg-gray-400 text-white cursor-not-allowed w-full'
                  : 'bg-red-600 text-white hover:bg-red-700 shadow-red-600/30 w-full'
              }`}
            >
              {cart.isBusy ? (
                <span className="flex items-center justify-center gap-2">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                  Добавление...
                </span>
              ) : !isAvailable ? (
                'Недоступно'
              ) : (
                'Добавить в корзину'
              )}
            </button>
          )}
        </div>

        <button
          onClick={() => void cart.buyNow()}
          className="w-full bg-red-200 text-red-700 py-3 rounded-lg font-semibold hover:bg-red-300 transition-colors whitespace-nowrap"
        >
          Купить сейчас
        </button>

        <button
          onClick={() => cart.goToCheckout()}
          className="w-full bg-white border-2 border-gray-300 text-gray-900 py-3 rounded-lg font-semibold hover:border-red-600 hover:bg-red-50 transition-colors whitespace-nowrap flex items-center justify-center gap-2"
        >
          <Calendar className="size-[1em] text-lg" />
          Оплата частями
        </button>
      </div>
    </div>
  );
}
