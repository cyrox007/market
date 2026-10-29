import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Check, ChevronsRight, Copy, ShoppingCart, Star } from 'lucide-react';
import { Link } from 'react-router-dom';
import { QuantityStepper } from '../../../components/ui/composites';
import { Badge, Button, Tooltip } from '../../../components/ui/primitives';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import { cn } from '../../../lib/cn';
import { plural } from '../../../lib/plural';
import type { VariantAttributeView } from '../hooks/useVariantSelection';
import { availabilityOf } from '../lib/availability';
import { bundleParts } from '../lib/bundle';
import { formatPrice, formatRating } from '../lib/productText';
import { isSellableOffer } from '../lib/variants';
import BundleComposition from './BundleComposition';
import VariantPicker from './VariantPicker';

export interface BuyBlockCart {
  quantity: number;
  isBusy: boolean;
  error: string | null;
  add: () => void;
  increment: () => void;
  decrement: () => void;
}

interface Props {
  product: ProductDetail;
  selectedVariant: ProductVariant | null;
  attributes: VariantAttributeView[];
  onSelectValue: (attributeSlug: string, valueSlug: string) => void;
  /** Пока перезагружается товар (смена региона) */
  disabled?: boolean;
  /** Город покупателя — в плашке остатка */
  city: string | null;
  cart: BuyBlockCart;
  onGoToReviews: () => void;
  onOneClick: () => void;
  /** «Сравнить» и «Избранное» справа от названия */
  titleActions?: ReactNode;
  /** Сроки доставки и самовывоза — под кнопками */
  delivery?: ReactNode;
}

const COPIED_MS = 2000;

/** Колонка покупки справа от галереи (Figma 669:43396, 919:75723) */
export default function BuyBlock({
  product,
  selectedVariant,
  attributes,
  onSelectValue,
  disabled = false,
  city,
  cart,
  onGoToReviews,
  onOneClick,
  titleActions,
  delivery,
}: Props) {
  const item = selectedVariant ?? product;
  const availability = availabilityOf(item);
  const sku = selectedVariant?.sku || product.sku;
  const price = item.price ?? 0;
  const oldPrice = item.old_price && item.old_price > price ? item.old_price : null;
  const discount = oldPrice ? Math.round((1 - price / oldPrice) * 100) : 0;
  const reviews = product.reviews_count ?? 0;
  const notForSale = Boolean(
    product.is_sellable_bundle && selectedVariant && !isSellableOffer(selectedVariant, true),
  );
  const parts = notForSale ? [] : bundleParts(product, selectedVariant);

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <div className="flex flex-col gap-3">
        <div className="flex items-start gap-6">
          <h1 className="min-w-0 flex-1 text-20 font-semibold text-ink">{product.name}</h1>
          {titleActions}
        </div>
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
          {sku && <ArticleCopy sku={sku} />}
          <button
            type="button"
            onClick={onGoToReviews}
            className="flex items-center gap-2 text-14 transition-opacity hover:opacity-70"
          >
            <span className="flex items-center gap-1 font-semibold text-ink">
              <Star aria-hidden="true" className="size-4 fill-brand-yellow text-brand-yellow" />
              {reviews > 0 && formatRating(product.rating ?? 0)}
            </span>
            <span className="font-medium text-ink-secondary">
              {reviews > 0
                ? `${reviews} ${plural(reviews, ['отзыв', 'отзыва', 'отзывов'])}`
                : 'Нет отзывов'}
            </span>
          </button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {availability.status === 'in_stock' && (
          <Badge variant="info" size="status">
            В наличии
          </Badge>
        )}
        {availability.status === 'backorder' && (
          <Badge variant="new" size="status">
            Под заказ
          </Badge>
        )}
        {availability.status === 'out' && (
          <Badge variant="neutral" size="status">
            Нет в наличии
          </Badge>
        )}
        {availability.stock !== null && (
          <Badge variant="neutral" size="status">
            {city ? `Остаток в г. ${city}: ` : 'Остаток: '}
            {availability.stock} шт.
          </Badge>
        )}
      </div>

      {notForSale ? (
        <p className="text-16 font-medium text-ink-secondary animate-fadeIn motion-reduce:animate-none">
          Этот вариант комплекта пока не продаётся
        </p>
      ) : (
        <div className="flex flex-wrap items-center gap-2">
          <span className="flex items-center gap-1.5 whitespace-nowrap">
            <span className={cn('text-24 font-bold', oldPrice ? 'text-brand-red' : 'text-ink')}>
              {formatPrice(price)}
            </span>
            {oldPrice && (
              <span className="text-14 font-medium text-ink-secondary line-through">
                {formatPrice(oldPrice)}
              </span>
            )}
          </span>
          {discount > 0 && (
            <span className="rounded-pill bg-brand-yellow px-2 py-0.5 text-14 text-ink">
              -{discount} %
            </span>
          )}
        </div>
      )}

      <VariantPicker attributes={attributes} onSelect={onSelectValue} disabled={disabled} />

      {parts.length > 0 && <BundleComposition key={selectedVariant?.id} parts={parts} />}

      {delivery}

      <div className="mt-auto flex flex-col gap-3 pt-4">
        {cart.error && (
          <p
            role="alert"
            className="rounded-btn bg-brand-red/10 px-4 py-3 text-14 text-brand-red animate-fadeIn motion-reduce:animate-none"
          >
            {cart.error}
          </p>
        )}
        {cart.quantity > 0 && !notForSale ? (
          <div className="flex items-center gap-2 animate-fadeIn motion-reduce:animate-none">
            <QuantityStepper
              tone="yellow"
              value={cart.quantity}
              min={0}
              max={availability.maxQuantity}
              disabled={cart.isBusy}
              onChange={(next) => (next > cart.quantity ? cart.increment() : cart.decrement())}
            />
            <Link
              to="/cart"
              className={cn(
                'inline-flex h-[52px] flex-1 items-center justify-center gap-1 rounded-btn bg-brand-yellow px-4',
                'whitespace-nowrap text-16 font-medium text-ink shadow-card outline-none',
                'transition-colors motion-reduce:transition-none',
                'hover:bg-brand-green hover:text-ink-inverse focus-visible:bg-brand-green focus-visible:text-ink-inverse',
              )}
            >
              Перейти в корзину
              <ChevronsRight aria-hidden="true" className="size-5" />
            </Link>
          </div>
        ) : (
          <Button
            size="lg"
            shape="rounded"
            fullWidth
            className="shadow-card"
            leftIcon={<ShoppingCart aria-hidden="true" className="size-5" />}
            isLoading={cart.isBusy}
            disabled={availability.status === 'out' || notForSale}
            onClick={cart.add}
          >
            Добавить в корзину
          </Button>
        )}
        <Button
          size="lg"
          variant="outline"
          shape="rounded"
          fullWidth
          disabled={notForSale}
          onClick={onOneClick}
        >
          Купить в 1 клик
        </Button>
      </div>
    </div>
  );
}

function ArticleCopy({ sku }: { sku: string }) {
  const [copied, setCopied] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  useEffect(() => () => clearTimeout(timer.current), []);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(sku);
      setCopied(true);
      clearTimeout(timer.current);
      timer.current = setTimeout(() => setCopied(false), COPIED_MS);
    } catch {
      // Буфер недоступен (нет https или запрет) — артикул виден и так
    }
  };

  return (
    <span className="flex items-center gap-1.5 text-14 font-semibold text-ink-secondary">
      <span>Арт.: {sku}</span>
      <Tooltip text={copied ? 'Скопировано' : 'Скопировать'} open={copied}>
        <button
          type="button"
          aria-label="Скопировать артикул"
          onClick={copy}
          className="-m-1 rounded-badge p-1 text-ink transition-colors hover:text-brand-green"
        >
          {copied ? (
            <Check aria-hidden="true" className="size-6 animate-photoIn text-brand-green" />
          ) : (
            <Copy aria-hidden="true" className="size-6" />
          )}
        </button>
      </Tooltip>
      <span role="status" className="sr-only">
        {copied ? 'Артикул скопирован' : ''}
      </span>
    </span>
  );
}
