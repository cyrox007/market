import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeftRight, Heart, ImageIcon, ShoppingCart } from 'lucide-react';
import type { Product } from '../../../lib/api';
import { isVariableParent } from '../../../utils/cartProduct';

interface CatalogProductCardV2Props {
  product: Product;
  isFavorite?: boolean;
  isInCompare?: boolean;
  onAddToCart?: (productId: number) => void | Promise<void>;
  onToggleFavorite?: (product: Product) => void | Promise<void>;
  onToggleCompare?: (product: Product) => void | Promise<void>;
  onPrefetch?: () => void;
  priority?: boolean;
}

const formatPrice = (price: number) =>
  new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(price) + ' ₽';

export default function CatalogProductCardV2({
  product,
  isFavorite = false,
  isInCompare = false,
  onAddToCart,
  onToggleFavorite,
  onToggleCompare,
  onPrefetch,
  priority = false,
}: CatalogProductCardV2Props) {
  const navigate = useNavigate();
  const [isAdding, setAdding] = useState(false);
  const image = product.thumbnail || product.image;
  const variableParent = isVariableParent(product);
  const unavailable = !variableParent && !product.in_stock && !product.backorder;

  const handleAdd = async () => {
    if (variableParent) {
      navigate(`/product/${product.slug}`);
      return;
    }
    if (!onAddToCart || isAdding || unavailable) return;

    try {
      setAdding(true);
      await onAddToCart(product.id);
    } finally {
      setAdding(false);
    }
  };

  const colors = product.colors?.filter((color) => color.code).slice(0, 4) ?? [];

  return (
    <article
      className="group min-w-0"
      onMouseEnter={onPrefetch}
      onFocusCapture={onPrefetch}
    >
      <div className="relative aspect-[292/270] overflow-hidden rounded-[12px] bg-surface-grey">
        <Link to={`/product/${product.slug}`} className="block h-full w-full">
          {image ? (
            <img
              src={image}
              alt={product.name}
              className="h-full w-full object-cover transition-transform duration-slow group-hover:scale-[1.015]"
              loading={priority ? 'eager' : 'lazy'}
              fetchPriority={priority ? 'high' : undefined}
              decoding="async"
              sizes="(max-width: 549px) calc(100vw - 32px), (max-width: 979px) 46vw, 292px"
            />
          ) : (
            <span className="flex h-full w-full items-center justify-center text-ink-secondary">
              <ImageIcon className="size-9" />
            </span>
          )}
        </Link>

        <div className="absolute right-3 top-3 z-10 flex gap-2 max-vsm:right-2 max-vsm:top-2">
          {onToggleCompare ? (
            <button
              type="button"
              aria-label={isInCompare ? 'Убрать из сравнения' : 'Добавить к сравнению'}
              onClick={() => onToggleCompare(product)}
              className={[
                'flex size-10 items-center justify-center rounded-full bg-surface shadow-btn transition-colors max-vsm:size-9',
                isInCompare ? 'text-brand-green' : 'text-ink',
              ].join(' ')}
            >
              <ArrowLeftRight className="size-5 max-vsm:size-[18px]" />
            </button>
          ) : null}

          {onToggleFavorite ? (
            <button
              type="button"
              aria-label={isFavorite ? 'Убрать из избранного' : 'Добавить в избранное'}
              onClick={() => onToggleFavorite(product)}
              className={[
                'flex size-10 items-center justify-center rounded-full bg-surface shadow-btn transition-colors max-vsm:size-9',
                isFavorite ? 'text-brand-red' : 'text-ink',
              ].join(' ')}
            >
              <Heart
                className="size-5 max-vsm:size-[18px]"
                fill={isFavorite ? 'currentColor' : 'none'}
              />
            </button>
          ) : null}
        </div>

        {product.stock_label ? (
          <div className="absolute left-3 top-3 z-10 max-w-[58%] rounded-pill bg-brand-green px-3 py-1 text-12 font-semibold text-ink-inverse max-vsm:left-2 max-vsm:top-2 max-vsm:px-2 max-vsm:text-10">
            {product.stock_label}
          </div>
        ) : null}

        {colors.length ? (
          <div className="absolute right-3 top-[58px] z-10 flex flex-col gap-1.5 rounded-pill bg-surface/90 p-1.5 shadow-btn backdrop-blur-sm max-vsm:right-2 max-vsm:top-[52px]">
            {colors.map((color, index) => (
              <span
                key={color.slug || color.name || index}
                title={color.name || undefined}
                className="size-6 rounded-full border border-surface-border max-vsm:size-5"
                style={{ backgroundColor: color.code || '#E8E5E1' }}
              />
            ))}
          </div>
        ) : null}

        <button
            type="button"
            aria-label={
              unavailable
                ? 'Товар недоступен'
                : variableParent
                  ? 'Выбрать вариант'
                  : 'Добавить в корзину'
            }
            disabled={isAdding || unavailable}
            onClick={handleAdd}
            className={[
              'absolute bottom-3 right-3 z-10 flex size-11 items-center justify-center rounded-full text-ink shadow-btn transition-transform max-vsm:bottom-2 max-vsm:right-2 max-vsm:size-10',
              unavailable
                ? 'cursor-not-allowed bg-surface-border text-ink-secondary'
                : 'bg-brand-yellow hover:scale-105',
              isAdding ? 'cursor-wait opacity-60' : '',
            ].join(' ')}
          >
            <ShoppingCart className="size-5 max-vsm:size-[18px]" />
          </button>
      </div>

      <Link
        to={`/product/${product.slug}`}
        className="mt-3 block line-clamp-2 min-h-[40px] text-16 leading-[1.25] text-ink transition-colors hover:text-brand-green max-vsm:mt-2 max-vsm:min-h-0 max-vsm:text-14"
      >
        {product.name}
      </Link>

      <div className="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <span className="text-18 font-semibold text-ink max-vsm:text-16">
          {formatPrice(product.price)}
        </span>
        {product.old_price ? (
          <span className="text-13 text-ink-secondary line-through max-vsm:text-12">
            {formatPrice(product.old_price)}
          </span>
        ) : null}
      </div>
    </article>
  );
}
