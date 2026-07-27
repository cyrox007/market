import { memo, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import ProductLink from './ProductLink';
import type { Product } from '../../lib/api';
import { isVariableParent } from '../../utils/cartProduct';

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
    // Когда приходит актуальное значение из родителя/SWR, сбрасываем локальный optimistic state.
    setOptimisticCartQuantity(null);
  }, [cartQuantity]);

  useEffect(() => {
    return () => {
      if (navigationLoaderTimerRef.current !== null) {
        window.clearTimeout(navigationLoaderTimerRef.current);
      }
    };
  }, []);

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('ru-RU').format(price) + ' ₽';
  };

  const variableParent = isVariableParent(product);

  const handleButtonClick = async (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

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

  const handleIncreaseClick = async (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (isAdjustingCart) return;
    try {
      setIsAdjustingCart(true);
      setOptimisticCartQuantity((prev) => (prev ?? cartQuantity) + 1);
      if (onIncreaseCart) {
        await onIncreaseCart(product.id);
        return;
      }
      if (onAddToCart) {
        await onAddToCart(product.id);
      }
    } catch {
      setOptimisticCartQuantity(null);
      // toast в useCartActions
    } finally {
      setIsAdjustingCart(false);
    }
  };

  const handleDecreaseClick = async (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (!onDecreaseCart || isAdjustingCart) return;
    try {
      setIsAdjustingCart(true);
      setOptimisticCartQuantity((prev) => Math.max(0, (prev ?? cartQuantity) - 1));
      await onDecreaseCart(product.id);
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
    if (onToggleFavorite) {
      onToggleFavorite(product);
    }
  };

  const handleCompareClick = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (onToggleCompare) {
      onToggleCompare(product);
    }
  };

  const handleProductLinkClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (e.defaultPrevented) return;
    // Для открытия в новой вкладке оставляем стандартное поведение и без локального loader.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    // Показываем loader только если переход реально "долгий", чтобы не было лишнего мигания.
    navigationLoaderTimerRef.current = window.setTimeout(() => {
      setShowNavigationLoader(true);
    }, 180);
  };

  return (
    <div
      className={`bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-shadow flex flex-col ${className}`}
      onMouseEnter={onMouseEnter}
      onMouseLeave={onMouseLeave}
    >
      <ProductLink
        to={`/product/${product.slug}`}
        className={`block flex-1 flex flex-col transition-opacity ${showNavigationLoader ? 'opacity-85' : ''}`}
        onClick={handleProductLinkClick}
      >
        <div className="relative h-48 md:h-80 overflow-hidden">
          {product.thumbnail || product.image_hd || product.image ? (
            <img
              src={product.thumbnail || product.image_hd || product.image}
              alt={product.name}
              className="w-full h-full object-cover object-top transition-all duration-300"
              loading={priority ? 'eager' : 'lazy'}
              decoding="async"
              fetchPriority={priority ? 'high' : undefined}
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              <i className="ri-image-line text-3xl text-gray-400"></i>
            </div>
          )}
          <div className="absolute top-2 md:top-3 right-2 md:right-3 flex gap-2">
            {onToggleCompare && (
              <button
                onClick={handleCompareClick}
                className={`w-8 h-8 md:w-9 md:h-9 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 cursor-pointer transition-colors ${isInCompare ? 'bg-red-50' : ''
                  }`}
              >
                <i className={`text-base md:text-lg ${isInCompare
                    ? 'ri-scales-3-fill text-red-600'
                    : 'ri-scales-3-line text-gray-600'
                  }`}></i>
              </button>
            )}
            {onToggleFavorite && (
              <button
                onClick={handleFavoriteClick}
                className={`w-8 h-8 md:w-9 md:h-9 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 cursor-pointer transition-colors ${isFavorite ? 'bg-red-50' : ''
                  }`}
              >
                <i className={`text-base md:text-lg ${isFavorite
                    ? 'ri-heart-fill text-red-500'
                    : 'ri-heart-line text-red-600'
                  }`}></i>
              </button>
            )}
          </div>
          {showNavigationLoader && (
            <div className="absolute inset-0 bg-white/45 flex items-center justify-center pointer-events-none">
              <div className="w-8 h-8 border-2 border-gray-300 border-t-red-600 rounded-full animate-spin" />
            </div>
          )}
        </div>
        <div className="p-3 md:p-4 flex-1 flex flex-col">
          {product.category && (
            <p className="text-xs text-gray-500 mb-1">{product.category.name}</p>
          )}
          <h3 className="font-semibold text-sm md:text-base mb-2 line-clamp-2 flex-1">{product.name}</h3>
          <div className="flex items-center gap-2 mb-2">
            <span className="text-lg md:text-xl font-bold text-red-600">{formatPrice(product.price)}</span>
            {product.old_price && (
              <span className="text-xs text-gray-400 line-through">{formatPrice(product.old_price)}</span>
            )}
          </div>
        </div>
      </ProductLink>
      {(onAddToCart || variableParent) && (
        <div className="px-3 md:px-4 pb-3 md:pb-4 mt-auto">
          {variableParent ? (
            <button
              onClick={handleButtonClick}
              className="w-full py-2 md:py-2.5 rounded-lg font-medium transition-colors whitespace-nowrap text-xs md:text-sm bg-red-600 text-white hover:bg-red-700"
            >
              Выбрать
            </button>
          ) : (!product.in_stock && !product.backorder) ? (
            <button disabled className="...">
              Недоступно
            </button>
          ) : (product.backorder && (product.stock ?? 0) === 0) ? (
            <button className="w-full py-2 md:py-2.5 rounded-lg font-medium transition-colors whitespace-nowrap text-xs md:text-sm bg-yellow-600 text-white hover:bg-yellow-700">
              Под заказ
            </button>
          ) : displayedCartQuantity > 0 ? (
            <div className="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2">
              <button
                onClick={handleDecreaseClick}
                disabled={isAdjustingCart}
                className="w-7 h-7 flex items-center justify-center rounded-md hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i className="ri-subtract-line"></i>
              </button>
              <span className="flex-1 text-center font-semibold text-sm md:text-base">{displayedCartQuantity}</span>
              <button
                onClick={handleIncreaseClick}
                disabled={isAdjustingCart}
                className="w-7 h-7 flex items-center justify-center rounded-md hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i className="ri-add-line"></i>
              </button>
            </div>
          ) : (
            <button
              onClick={handleButtonClick}
              disabled={isAddingToCart}
              className={`w-full py-2 md:py-2.5 rounded-lg font-medium transition-colors whitespace-nowrap text-xs md:text-sm disabled:opacity-70 ${addedToCart
                  ? 'bg-green-600 text-white hover:bg-green-700'
                  : 'bg-red-600 text-white hover:bg-red-700'
                }`}
            >
              {isAddingToCart ? 'Добавляем…' : addedToCart ? 'Добавлено' : 'В корзину'}
            </button>
          )}
        </div>
      )}
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
    prev.product.thumbnail === next.product.thumbnail &&
    prev.product.image_hd === next.product.image_hd &&
    prev.product.image === next.product.image &&
    prev.addedToCart === next.addedToCart &&
    prev.cartQuantity === next.cartQuantity &&
    prev.isFavorite === next.isFavorite &&
    prev.isInCompare === next.isInCompare &&
    prev.className === next.className &&
    prev.priority === next.priority
  );
}

export default memo(ProductCard, areEqual);
