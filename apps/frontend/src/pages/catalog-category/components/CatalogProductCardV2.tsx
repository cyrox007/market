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

  const handleAdd = async () => {
    if (variableParent) {
      navigate(`/product/${product.slug}`);
      return;
    }
    if (!onAddToCart || isAdding) return;

    try {
      setAdding(true);
      await onAddToCart(product.id);
    } finally {
      setAdding(false);
    }
  };

  return (
    <article className="group min-w-0" onMouseEnter={onPrefetch}>
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
            />
          ) : (
            <span className="flex h-full w-full items-center justify-center text-ink-secondary">
              <ImageIcon className="size-9" />
            </span>
          )}
        </Link>

        <div className="absolute right-3 top-3 flex gap-2 max-vsm:right-2 max-vsm:top-2">
          {onToggleCompare ? (
            <button
              type="button"
              aria-label={isInCompare ? 'Убрать из сравнения' : 'Добавить к сравнению'}
              onClick={() => onToggleCompare(product)}
              className={[
                'flex size-10 items-center justify-center rounded-full bg-surface shadow-btn transition-colors',
                isInCompare ? 'text-brand-green' : 'text-ink',
              ].join(' ')}
            >
              <ArrowLeftRight className="size-5" />
            </button>
          ) : null}
          {onToggleFavorite ? (
            <button
              type="button"
              aria-label={isFavorite ? 'Убрать из избранного' : 'Добавить в избранное'}
              onClick={() => onToggleFavorite(product)}
              className={[
                'flex size-10 items-center justify-center rounded-full bg-surface shadow-btn transition-colors',
                isFavorite ? 'text-brand-red' : 'text-ink',
              ].join(' ')}
            >
              <Heart className="size-5" fill={isFavorite ? 'currentColor' : 'none'} />
            </button>
          ) : null}
        </div>

        {product.stock_label ? (
          <div className="absolute left-3 top-3 max-w-[60%] rounded-pill bg-brand-green px-3 py-1 text-12 font-semibold text-ink-inverse">
            {product.stock_label}
          </div>
        ) : null}

        {(onAddToCart || variableParent) && (
          <button
            type="button"
            aria-label={variableParent ? 'Выбрать вариант' : 'Добавить в корзину'}
            disabled={isAdding}
            onClick={handleAdd}
            className="absolute bottom-3 right-3 flex size-11 items-center justify-center rounded-full bg-brand-yellow text-ink shadow-btn transition-transform hover:scale-105 disabled:cursor-wait disabled:opacity-60 max-vsm:bottom-2 max-vsm:right-2"
          >
            <ShoppingCart className="size-5" />
          </button>
        )}
      </div>

      <Link
        to={`/product/${product.slug}`}
        className="mt-3 block min-h-[40px] text-16 leading-tight text-ink hover:text-brand-green max-vsm:mt-2 max-vsm:text-14"
      >
        {product.name}
      </Link>

      <div className="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <span className="text-18 font-semibold text-ink max-vsm:text-16">
          {formatPrice(product.price)}
        </span>
        {product.old_price ? (
          <span className="text-13 text-ink-secondary line-through">
            {formatPrice(product.old_price)}
          </span>
        ) : null}
      </div>

      {product.colors?.length ? (
        <div className="mt-2 flex gap-1.5" aria-label="Доступные цвета">
          {product.colors.slice(0, 4).map((color, index) => (
            <span
              key={color.slug || color.name || index}
              title={color.name || undefined}
              className="size-5 rounded-swatch border border-surface-border"
              style={{ backgroundColor: color.code || '#E8E5E1' }}
            />
          ))}
        </div>
      ) : null}
    </article>
  );
}
