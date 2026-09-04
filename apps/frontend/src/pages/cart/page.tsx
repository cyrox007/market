import { useState, useEffect, useMemo, useRef } from 'react';
import { Link } from 'react-router-dom';
import ProductLink from '../../components/ui/ProductLink';
import ProductCard from '../../components/ui/ProductCard';
import CartItemVariants from '../../components/cart/CartItemVariants';
import { useCart } from '../../hooks/useCart';
import { useCartActions } from '../../hooks/useCartActions';
import { useRegion } from '../../hooks/useRegion';
import { useWishlistAndCompare } from '../../hooks/useWishlistAndCompare';
import { api } from '../../lib/api';
import type { Product } from '../../lib/api';
import { ChevronRight, ImageIcon, Minus, Plus, ShoppingCart, X } from 'lucide-react';

export default function Cart() {
  const { cart, isLoading, error, updateQuantity, updateVariant, removeFromCart, reloadCart, addToCart } = useCart();
  const { changeProductQuantity } = useCartActions();
  const { region } = useRegion();
  const { wishlistProductIds: favorites, compareProductIds: compareList, mutateWishlist, mutateCompare } = useWishlistAndCompare();

  const promoApplied = false;
  const [updatingItemId, setUpdatingItemId] = useState<number | null>(null);
  const [removingItemId, setRemovingItemId] = useState<number | null>(null);
  const [recommendedProducts, setRecommendedProducts] = useState<Product[]>([]);
  const [isLoadingRecommended, setIsLoadingRecommended] = useState(false);
  const [addingToCartId, setAddingToCartId] = useState<number | null>(null);
  /** Подборка «Рекомендуем» загружается один раз за визит страницы, не при каждом изменении корзины */
  const recommendedLoadedRef = useRef(false);

  const handleUpdateQuantity = async (itemId: number, newQuantity: number) => {
    if (newQuantity < 1) return;
    setUpdatingItemId(itemId);
    try {
      await updateQuantity(itemId, newQuantity);
    } catch (err) {
      console.error('Failed to update quantity:', err);
    } finally {
      setUpdatingItemId(null);
    }
  };

  const handleVariantChange = async (
    itemId: number,
    quantity: number,
    variationAttributes?: { attribute_slug: string; value_slug: string }[]
  ) => {
    const cartItem = cart?.items.find(item => item.id === itemId);
    if (!cartItem) {
      console.error('Item not found in cart:', itemId);
      return;
    }

    setUpdatingItemId(itemId);
    try {
      await updateVariant(itemId, quantity, variationAttributes);
      await reloadCart();
    } catch (err: any) {
      console.error('Failed to update variant:', err);
      await reloadCart().catch(reloadErr => console.error('Failed to reload cart after error:', reloadErr));
      throw err;
    } finally {
      setUpdatingItemId(null);
    }
  };

  const handleRemoveItem = async (itemId: number) => {
    setRemovingItemId(itemId);
    try {
      await removeFromCart(itemId);
    } catch (err) {
      console.error('Failed to remove item:', err);
    } finally {
      setRemovingItemId(null);
    }
  };

  // Подборка из админки (slug: recommended) — один запрос при первом появлении товаров в корзине
  useEffect(() => {
    if (isLoading || !cart || cart.items.length === 0) {
      return;
    }
    if (recommendedLoadedRef.current) {
      return;
    }
    recommendedLoadedRef.current = true;

    const loadRecommendedProducts = async () => {
      setIsLoadingRecommended(true);
      try {
        const response = await api.products.collection('recommended');
        setRecommendedProducts(response.data || []);
      } catch (err) {
        console.error('Failed to load recommended products:', err);
        setRecommendedProducts([]);
      } finally {
        setIsLoadingRecommended(false);
      }
    };

    loadRecommendedProducts();
  }, [isLoading, cart?.items.length]);

  const handleAddRecommendedToCart = async (productId: number) => {
    setAddingToCartId(productId);
    try {
      await addToCart(productId, 1);
    } catch (err) {
      console.error('Failed to add product to cart:', err);
    } finally {
      setAddingToCartId(null);
    }
  };

  const recommendedCartQuantityByProductId = useMemo(() => {
    const quantities: Record<number, number> = {};
    for (const item of cart?.items ?? []) {
      quantities[item.product_id] = (quantities[item.product_id] ?? 0) + item.quantity;
    }
    return quantities;
  }, [cart?.items]);

  const updateRecommendedCartQuantityByProduct = async (productId: number, delta: number) => {
    const item = recommendedProducts.find((p) => p.id === productId);
    if (!item) return;
    try {
      await changeProductQuantity(item, delta);
    } catch {
      // toast в useCartActions
    }
  };

  // Вспомогательная функция для определения ID товара для избранного
  const getProductIdForWishlist = (product: Product): number => {
    return product.id;
  };

  // Вспомогательная функция для определения ID товара для сравнения
  const getProductIdForCompare = (product: Product): number => {
    if (product.is_variable && !product.is_variant && product.first_available_variant_id) {
      return product.first_available_variant_id;
    }
    return product.id;
  };

  const toggleFavorite = async (product: Product) => {
    try {
      const productIdToAdd = getProductIdForWishlist(product);
      if (!productIdToAdd || productIdToAdd === 0) {
        console.error('Invalid product ID for wishlist:', product);
        return;
      }
      await api.wishlist.toggle(productIdToAdd);
      await mutateWishlist();
    } catch (error) {
      console.error('Failed to toggle favorite:', error);
    }
  };

  const toggleCompare = async (product: Product) => {
    try {
      const productIdToAdd = getProductIdForCompare(product);
      const isInCompare = compareList.includes(productIdToAdd);
      if (isInCompare) {
        await api.compare.remove(productIdToAdd);
      } else {
        await api.compare.add(productIdToAdd);
      }
      await mutateCompare();
    } catch (error: any) {
      console.error('Failed to toggle compare:', error);
      if (error.status === 422) {
        alert(error.data?.message || 'Не удалось добавить товар в сравнение.');
      }
    }
  };

  const subtotal = cart?.subtotal || 0;
  const discount = promoApplied ? subtotal * 0.1 : 0;
  const total = subtotal - discount;
  const cartItems = cart?.items || [];

  if (isLoading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="flex items-center justify-center py-16">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <p className="font-semibold">Ошибка загрузки корзины</p>
            <p className="text-sm">{error}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">

      <div className="max-w-7xl mx-auto px-4 py-6">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-sm mb-2">
          <Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Корзина</span>
        </div>

        <h1 className="text-4xl font-bold mb-8">Корзина</h1>

        {cartItems.length === 0 ? (
          <div className="text-center py-16">
            <div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <ShoppingCart className="size-[1em] text-6xl text-gray-400" />
            </div>
            <h2 className="text-2xl font-bold mb-4">Корзина пуста</h2>
            <p className="text-gray-600 mb-8">Добавьте товары из каталога</p>
            <Link to="/catalog" className="inline-block bg-red-600 text-white px-8 py-4 rounded-lg font-medium text-lg hover:bg-red-700 transition-colors whitespace-nowrap">
              Перейти в каталог
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Cart Items */}
            <div className="lg:col-span-2 space-y-4">

              {cartItems.map((item) => (
                <div key={item.id} className="bg-white border border-gray-200 rounded-2xl p-6">
                  <div className="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    {item.slug ? (
                      <ProductLink to={`/product/${item.slug}`} className="w-full sm:w-32 h-40 sm:h-24 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0 hover:opacity-90 transition-opacity block">
                        {item.image ? (
                          <img src={item.image} alt={item.name} className="w-full h-full object-cover object-top" />
                        ) : (
                          <div className="w-full h-full flex items-center justify-center">
                            <ImageIcon className="size-[1em] text-3xl text-gray-400" />
                          </div>
                        )}
                      </ProductLink>
                    ) : (
                      <div className="w-full sm:w-32 h-40 sm:h-24 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                        {item.image ? (
                          <img src={item.image} alt={item.name} className="w-full h-full object-cover object-top" />
                        ) : (
                          <div className="w-full h-full flex items-center justify-center">
                            <ImageIcon className="size-[1em] text-3xl text-gray-400" />
                          </div>
                        )}
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between mb-2">
                        {item.slug ? (
                          <ProductLink to={`/product/${item.slug}`} className="font-bold text-lg hover:text-red-600 transition-colors flex-1">
                            {item.name}
                          </ProductLink>
                        ) : (
                          <h3 className="font-bold text-lg flex-1">{item.name}</h3>
                        )}
                        <button
                          onClick={() => handleRemoveItem(item.id)}
                          disabled={removingItemId === item.id}
                          className="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-600 cursor-pointer disabled:opacity-50"
                        >
                          <X className="size-[1em] text-2xl" />
                        </button>
                      </div>
                      {/* Отображение текущих вариаций и возможность их изменения */}
                      <CartItemVariants
                        item={item}
                        regionId={region?.id}
                        isUpdating={updatingItemId === item.id}
                        onVariantChange={handleVariantChange}
                      />
                      {!item.is_variant && (item.color || item.size) && (
                        <div className="text-sm text-gray-600 mb-3">
                          {item.color && (
                            <p>Цвет: {item.color.name || item.color.slug}</p>
                          )}
                          {item.size && (
                            <p>Размер: {item.size.name || item.size.slug}</p>
                          )}
                        </div>
                      )}
                      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-3">
                        <div className="flex items-center gap-3 w-full sm:w-auto">
                          <button
                            onClick={() => handleUpdateQuantity(item.id, item.quantity - 1)}
                            disabled={updatingItemId === item.id}
                            className="w-10 h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 cursor-pointer disabled:opacity-50 flex-shrink-0"
                          >
                            <Minus className="size-[1em]" />
                          </button>
                          <span className="w-12 text-center font-medium">{item.quantity}</span>
                          <button
                            onClick={() => handleUpdateQuantity(item.id, item.quantity + 1)}
                            disabled={updatingItemId === item.id}
                            className="w-10 h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 cursor-pointer disabled:opacity-50 flex-shrink-0"
                          >
                            <Plus className="size-[1em]" />
                          </button>
                        </div>
                        <div className="w-full sm:w-auto text-left sm:text-right">
                          <p className="text-2xl font-bold text-red-600">{item.total.toLocaleString()} ₽</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}

              {/* Promo Code */}
              {/* <div className="bg-gray-50 rounded-2xl p-6">
                <h3 className="font-bold mb-4">Промокод</h3>
                <div className="flex gap-3">
                  <input
                    type="text"
                    value={promoCode}
                    onChange={(e) => setPromoCode(e.target.value)}
                    disabled={promoApplied}
                    className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none disabled:bg-gray-100"
                    placeholder="Введите промокод"
                  />
                  <button
                    onClick={applyPromo}
                    disabled={promoApplied}
                    className="bg-red-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-400 whitespace-nowrap"
                  >
                    {promoApplied ? 'Применен' : 'Применить'}
                  </button>
                </div>
                {promoApplied && (
                  <p className="text-green-600 text-sm mt-2 flex items-center gap-2">
                    <Check className="size-[1em]" />
                    Промокод успешно применен! Скидка 10%
                  </p>
                )}
              </div> */}
            </div>

            {/* Order Summary */}
            <div className="lg:col-span-1">
              <div className="bg-white border border-gray-200 rounded-2xl p-6 sticky top-4">
                <h3 className="text-xl font-bold mb-6">Итого</h3>
                <div className="space-y-4 mb-6">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Товары ({cartItems.reduce((sum, item) => sum + item.quantity, 0)})</span>
                    <span className="font-medium">{subtotal.toLocaleString()} ₽</span>
                  </div>
                  {promoApplied && (
                    <div className="flex justify-between text-green-600">
                      <span>Скидка по промокоду</span>
                      <span className="font-medium">-{discount.toLocaleString()} ₽</span>
                    </div>
                  )}
                  <div className="border-t border-gray-200 pt-4">
                    <div className="flex justify-between items-center">
                      <span className="text-lg font-bold">Всего</span>
                      <span className="text-3xl font-bold text-red-600">{total.toLocaleString()} ₽</span>
                    </div>
                  </div>
                </div>

                <Link to="/checkout" className="block w-full bg-red-600 text-white py-4 rounded-lg font-medium text-center hover:bg-red-700 transition-colors mb-3 whitespace-nowrap">
                  Оформить заказ
                </Link>
                <Link to="/catalog" className="block w-full bg-white border-2 border-gray-300 text-gray-900 py-4 rounded-lg font-medium text-center hover:border-red-600 transition-colors whitespace-nowrap">
                  Продолжить покупки
                </Link>

                {/* Benefits */}
              </div>
            </div>
          </div>
        )}

        {/* Recommended Products */}
        {cartItems.length > 0 && (
          <div className="mt-16">
            <h2 className="text-3xl font-bold mb-8">Рекомендуем к покупке</h2>
            {isLoadingRecommended ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {[...Array(4)].map((_, idx) => (
                  <div key={idx} className="bg-white border border-gray-200 rounded-2xl overflow-hidden animate-pulse">
                    <div className="h-48 bg-gray-200"></div>
                    <div className="p-4">
                      <div className="h-4 bg-gray-200 rounded w-3/4 mb-3"></div>
                      <div className="h-6 bg-gray-200 rounded w-24 mb-4"></div>
                      <div className="h-10 bg-gray-200 rounded"></div>
                    </div>
                  </div>
                ))}
              </div>
            ) : recommendedProducts.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" data-product-shop>
                {recommendedProducts.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    onAddToCart={handleAddRecommendedToCart}
                    onIncreaseCart={(productId) => updateRecommendedCartQuantityByProduct(productId, 1)}
                    onDecreaseCart={(productId) => updateRecommendedCartQuantityByProduct(productId, -1)}
                    onToggleFavorite={toggleFavorite}
                    onToggleCompare={toggleCompare}
                    addedToCart={addingToCartId === product.id}
                    cartQuantity={recommendedCartQuantityByProductId[product.id] ?? 0}
                    isFavorite={favorites.includes(getProductIdForWishlist(product))}
                    isInCompare={compareList.includes(getProductIdForCompare(product))}
                  />
                ))}
              </div>
            ) : null}
          </div>
        )}
      </div>

    </div>
  );
}
