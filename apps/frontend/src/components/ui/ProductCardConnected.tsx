import ProductCard from './ProductCard';
import type { Product } from '../../lib/api';
import { useCartActions } from '../../hooks/useCartActions';
import {
  productIdForCompare,
  productIdForWishlist,
  useWishlistAndCompare,
} from '../../hooks/useWishlistAndCompare';
import { usePrefetchProduct } from '../../hooks/usePrefetchProduct';
import { getCartQuantityForProduct } from '../../utils/cartProduct';

interface ProductCardConnectedProps {
  product: Product;
  /** Первый ряд — картинка с высоким приоритетом для LCP */
  priority?: boolean;
  className?: string;
}

/**
 * Карточка, которая сама знает своё состояние: корзина, избранное, сравнение.
 * Страницы передают только товар. market-docs/34 §1, 35
 */
export default function ProductCardConnected({ product, priority, className }: ProductCardConnectedProps) {
  const { cart, addProductToCart, changeProductQuantity } = useCartActions();
  const { wishlistProductIds, compareProductIds, toggleWishlist, toggleCompare } = useWishlistAndCompare();
  const wishlistId = productIdForWishlist(product);
  const compareId = productIdForCompare(product);
  const prefetchProduct = usePrefetchProduct();

  return (
    <ProductCard
      product={product}
      priority={priority}
      className={className}
      onMouseEnter={() => prefetchProduct(product.slug)}
      cartQuantity={getCartQuantityForProduct(cart?.items, product)}
      onAddToCart={() => void addProductToCart(product, 1)}
      onIncreaseCart={() => changeProductQuantity(product, 1)}
      onDecreaseCart={() => changeProductQuantity(product, -1)}
      isFavorite={wishlistProductIds.includes(wishlistId)}
      isInCompare={compareProductIds.includes(compareId)}
      onToggleFavorite={() => toggleWishlist(wishlistId)}
      onToggleCompare={() => toggleCompare(compareId)}
    />
  );
}
