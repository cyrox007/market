import { memo, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeftRight, Heart, ImageIcon, ShoppingCart } from 'lucide-react';
import ProductLink from './ProductLink';
import { Badge, IconButton } from './primitives';
import { ColorSwatches, Price, QuantityStepper } from './composites';
import type { ColorSwatch } from './composites';
import type { Product } from '../../lib/api';
import { isVariableParent } from '../../utils/cartProduct';
import { cn } from '../../lib/cn';

interface ProductCardProps {
  product: Product;
  onAddToCart?: (productId: number) => void | Promise<void>;
  onIncreaseCart?: (productId: number) => void | Promise<void>;
  onDecreaseCart?: (productId: number) => void | Promise<void>;
  onToggleFavorite?: (product: Product) => void;
  onToggleCompare?: (product: Product) => void;
  addedToCart?: boolean;
  cartQuantity?: number;
  isFavorite?: boolean;
  isInCompare?: boolean;
  className?: string;
  onMouseEnter?: () => void;
  onMouseLeave?: () => void;
  /** Первый ряд карточек — загрузка изображения с высоким приоритетом для быстрого LCP */
  priority?: boolean;
}

/** Кнопки лежат поверх растянутой ссылки, иначе клик по ним уводил бы на товар */
const OVERLAY = 'relative z-10';

function toSwatches(product: Product): ColorSwatch[] {
  return (product.colors ?? [])
    .filter((color) => Boolean(color.code))
    .map((color) => ({ value: color.code as string, title: color.name ?? undefined }));
}

function ProductCard({
  product,
  onAddToCart,
  onIncreaseCart,
  onDecreaseCart,
  onToggleFavorite,
  onToggleCompare,
  addedToCart = false,
  cartQuantity = 0,
  isFavorite = false,
  isInCompare = false,
  className = '',
  onMouseEnter,
  onMouseLeave,
  priority = false,
}: ProductCardProps) {
  const navigate = useNavigate();
  const [isAdjustingCart, setIsAdjustingCart] = useState(false);
  const [isAddingToCart, setIsAddingToCart] = useState(false);
  const [showNavigationLoader, setShowNavigationLoader] = useState(false);
  const [optimisticCartQuantity, setOptimisticCartQuantity] = useState<number | null>(null);
  const displayedCartQuantity = optimisticCartQuantity ?? cartQuantity;
  const navigationLoaderTimerRef = useRef<number | null>(null);

  useEffect(() => {
    // Пришло актуальное значение из родителя — сбрасываем оптимистичное
    setOptimisticCartQuantity(null);
  }, [cartQuantity]);

  useEffect(() => {
    return () => {
      if (navigationLoaderTimerRef.current !== null) {
        window.clearTimeout(navigationLoaderTimerRef.current);
      }
    };
  }, []);

  const variableParent = isVariableParent(product);
  // image_hd в списке товаров API отдаёт null намеренно — в цепочке он лишний
  const image = product.thumbnail || product.image;
  const swatches = toSwatches(product);
  const unavailable = !variableParent && !product.in_stock && !product.backorder;

  const handleCartClick = async (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    // У товара с вариациями цвет и размер выбираются на его странице
    if (variableParent) {
      navigate(`/product/${product.slug}`);
      return;
    }
    if (!onAddToCart || isAddingToCart || isAdjustingCart) return;

    try {
      setIsAddingToCart(true);
      await onAddToCart(product.id);
    } catch {
      // toast в useCartActions
    } finally {
      setIsAddingToCart(false);
    }
  };

  const handleQuantityChange = async (next: number) => {
    if (isAdjustingCart) return;
    const current = displayedCartQuantity;
    if (next === current) return;

    try {
      setIsAdjustingCart(true);
      setOptimisticCartQuantity(Math.max(0, next));

      if (next > current) {
        if (onIncreaseCart) await onIncreaseCart(product.id);
        else if (onAddToCart) await onAddToCart(product.id);
        return;
      }
      if (onDecreaseCart) await onDecreaseCart(product.id);
    } catch {
      setOptimisticCartQuantity(null);
      // toast в useCartActions
    } finally {
      setIsAdjustingCart(false);
    }
  };

  const handleFavoriteClick = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    onToggleFavorite?.(product);
  };

  const handleCompareClick = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    onToggleCompare?.(product);
  };

  const handleProductLinkClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (e.defaultPrevented) return;
    // Открытие в новой вкладке оставляем стандартным и без индикатора
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    // Индикатор только если переход реально долгий, иначе лишнее мигание
    navigationLoaderTimerRef.current = window.setTimeout(() => {
      setShowNavigationLoader(true);
    }, 180);
  };

  return (
    <div
      className={cn('group relative isolate flex flex-col gap-3', className)}
      onMouseEnter={onMouseEnter}
      onMouseLeave={onMouseLeave}
    >
      <div className="relative flex aspect-square flex-col justify-between overflow-hidden rounded-btn border border-surface-stroke/[0.08] p-3">
        {image ? (
          <img
            src={image}
            alt=""
            className="absolute inset-0 size-full object-cover"
            loading={priority ? 'eager' : 'lazy'}
            decoding="async"
            fetchPriority={priority ? 'high' : undefined}
          />
        ) : (
          <div className="absolute inset-0 flex items-center justify-center bg-surface-grey">
            <ImageIcon className="size-8 text-ink-secondary" />
          </div>
        )}

        <div className={cn(OVERLAY, 'flex items-start justify-between')}>
          {product.stock_label ? (
            <Badge variant="info" size="sm">
              {product.stock_label}
            </Badge>
          ) : (
            <span />
          )}

          <div className="flex items-center gap-2">
            {onToggleCompare && (
              <IconButton
                label={isInCompare ? 'Убрать из сравнения' : 'Добавить к сравнению'}
                variant="white"
                isActive={isInCompare}
                elevated
                onClick={handleCompareClick}
              >
                <ArrowLeftRight className="size-5" />
              </IconButton>
            )}
            {onToggleFavorite && (
              <IconButton
                label={isFavorite ? 'Убрать из избранного' : 'В избранное'}
                variant="white"
                isActive={isFavorite}
                elevated
                onClick={handleFavoriteClick}
              >
                <Heart className="size-5" fill={isFavorite ? 'currentColor' : 'none'} />
              </IconButton>
            )}
          </div>
        </div>

        <div className={cn(OVERLAY, 'flex items-center justify-between gap-1')}>
          {displayedCartQuantity > 0 && !variableParent ? (
            <QuantityStepper
              value={displayedCartQuantity}
              min={0}
              max={product.backorder ? undefined : (product.stock ?? undefined)}
              onChange={handleQuantityChange}
            />
          ) : (
            <span />
          )}

          {(onAddToCart || variableParent) && (
            <IconButton
              label={variableParent ? 'Выбрать вариант' : 'В корзину'}
              variant="yellow"
              isActive={addedToCart}
              elevated
              disabled={unavailable || isAddingToCart}
              onClick={handleCartClick}
            >
              <ShoppingCart className="size-5" />
            </IconButton>
          )}
        </div>

        {showNavigationLoader && (
          <div className="pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-surface/45">
            <div className="size-8 animate-spin rounded-full border-2 border-surface-border border-t-brand-green" />
          </div>
        )}
      </div>

      <div className="flex flex-col gap-1.5 px-0.5">
        {/* after:inset-0 растягивает ссылку на всю карточку, не вкладывая в неё кнопки */}
        <ProductLink
          to={`/product/${product.slug}`}
          onClick={handleProductLinkClick}
          className="truncate text-18 font-medium text-ink outline-none after:absolute after:inset-0 focus-visible:underline"
        >
          {product.name}
        </ProductLink>

        <div className="flex items-end justify-between gap-2">
          <Price
            value={product.price}
            oldValue={product.old_price ?? undefined}
            from={variableParent}
          />
          {swatches.length > 0 && <ColorSwatches colors={swatches} />}
        </div>
      </div>
    </div>
  );
}

function areEqual(prev: ProductCardProps, next: ProductCardProps): boolean {
  return (
    prev.product.id === next.product.id &&
    prev.product.price === next.product.price &&
    prev.product.old_price === next.product.old_price &&
    prev.product.in_stock === next.product.in_stock &&
    prev.product.is_variable === next.product.is_variable &&
    prev.product.is_variant === next.product.is_variant &&
    prev.product.first_available_variant_id === next.product.first_available_variant_id &&
    prev.product.stock === next.product.stock &&
    prev.product.stock_label === next.product.stock_label &&
    prev.product.thumbnail === next.product.thumbnail &&
    prev.product.image === next.product.image &&
    prev.product.colors === next.product.colors &&
    prev.addedToCart === next.addedToCart &&
    prev.cartQuantity === next.cartQuantity &&
    prev.isFavorite === next.isFavorite &&
    prev.isInCompare === next.isInCompare &&
    prev.className === next.className &&
    prev.priority === next.priority
  );
}

export default memo(ProductCard, areEqual);
