import { useState } from 'react';
import useSWR from 'swr';
import { api, InteriorIdea, InteriorIdeaHotspot } from '@/lib/api';
import { useSSR } from '@/contexts/SSRContext';
import { useCartActions } from '@/hooks/useCartActions';

export default function InteriorIdeas() {
  const ssrData = useSSR();
  const initialRooms = ssrData?.home?.interiorIdeas || [];

  const [activeHotspot, setActiveHotspot] = useState<string | null>(null);
  const [favorites, setFavorites] = useState<number[]>([]);
  const [addedToCart, setAddedToCart] = useState<number[]>([]);
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [isAddingToCart, setIsAddingToCart] = useState<Record<number, boolean>>({});
  const { addProductToCart, updateQuantity: updateCartQuantity, cart } = useCartActions();

  // Используем SWR для загрузки идей интерьера
  const { data: interiorIdeasData, isLoading: loading } = useSWR(
    '/api/interior-ideas',
    () => api.interiorIdeas.list({ page: 1, per_page: 6 }),
    {
      fallbackData: initialRooms.length > 0 ? { data: initialRooms } : undefined,
      revalidateOnMount: initialRooms.length === 0,
      revalidateIfStale: true,
    }
  );

  const rooms = interiorIdeasData?.data || [];

  if (loading && rooms.length === 0) {
    return (
      <section className="py-16 md:py-24 bg-gray-50 relative z-10">
        <div className="max-w-7xl mx-auto px-4">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Идеи для интерьера</h2>
          </div>
          <div className="text-center">
            <p className="text-gray-500">Загрузка...</p>
          </div>
        </div>
      </section>
    );
  }

  if (!rooms || rooms.length === 0) {
    return null;
  }

  const toggleFavorite = (productId: number) => {
    setFavorites(prev =>
      prev.includes(productId)
        ? prev.filter(id => id !== productId)
        : [...prev, productId]
    );
  };

  const addToCart = async (product: InteriorIdeaHotspot['product']) => {
    if (!product) return;

    // Проверяем, не добавляем ли мы уже этот товар
    if (isAddingToCart[product.id]) return;

    try {
      setIsAddingToCart(prev => ({ ...prev, [product.id]: true }));

      const quantity = quantities[product.id] || 1;
      await addProductToCart(
        {
          id: product.id,
          name: product.name,
          slug: product.slug,
          price: product.price,
          in_stock: product.in_stock ?? true,
          is_variable: product.is_variable,
          is_variant: product.is_variant,
          first_available_variant_id: product.first_available_variant_id,
          thumbnail: product.thumbnail,
          image: product.image,
          sku: product.sku,
        },
        quantity,
      );

      setAddedToCart((prev) => (prev.includes(product.id) ? prev : [...prev, product.id]));
      if (!quantities[product.id]) {
        setQuantities((prev) => ({ ...prev, [product.id]: 1 }));
      }
    } catch {
      // toast показывается в useCartActions
    } finally {
      setIsAddingToCart(prev => {
        const newState = { ...prev };
        delete newState[product.id];
        return newState;
      });
    }
  };

  const updateQuantity = async (productId: number, delta: number) => {
    const newQuantity = Math.max(1, (quantities[productId] || 1) + delta);

    // Обновляем локальное состояние сразу для быстрого отклика
    setQuantities(prev => ({
      ...prev,
      [productId]: newQuantity
    }));

    // Если товар уже в корзине, обновляем количество через API
    if (addedToCart.includes(productId) && cart) {
      const cartItem = cart.items.find(item => item.product_id === productId);
      if (cartItem) {
        try {
          await updateCartQuantity(cartItem.id, newQuantity);
        } catch (error) {
          console.error('Ошибка обновления количества:', error);
          // Откатываем изменение при ошибке
          setQuantities(prev => ({
            ...prev,
            [productId]: quantities[productId] || 1
          }));
        }
      }
    }
  };

  const goToProduct = (productId: number, fullPath?: string | null) => {
    if (fullPath) {
      window.REACT_APP_NAVIGATE(fullPath);
    } else {
      window.REACT_APP_NAVIGATE(`/product/${productId}`);
    }
  };

  // Вариативный родительский товар — показываем "Выбрать" с переходом в товар, без добавления в корзину
  const isVariantParent = (product: InteriorIdeaHotspot['product']) =>
    product != null && product.is_variable === true && product.is_variant !== true;

  return (
    <section className="py-16 md:py-24 bg-gray-50 relative z-10">
      <div className="max-w-7xl mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="text-3xl md:text-4xl font-bold mb-4">Идеи для интерьера</h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {rooms.map((room) => (
            <div key={room.id} className="relative">
              {/* Room Image */}
              <div className="relative rounded-2xl overflow-visible shadow-xl">
                <img
                  src={room.image || room.image_main || ''}
                  alt={room.title || `Интерьер ${room.id}`}
                  className="w-full h-auto object-cover object-top rounded-2xl"
                />

                {/* Hotspots */}
                {room.hotspots?.filter(h => h.product).map((hotspot) => (
                  <div
                    key={hotspot.id}
                    className="absolute cursor-pointer"
                    style={{
                      left: `${hotspot.x}%`,
                      top: `${hotspot.y}%`,
                      transform: 'translate(-50%, -50%)',
                      zIndex: activeHotspot === `${room.id}-${hotspot.id}` ? 50 : 10
                    }}
                    onClick={(e) => {
                      e.stopPropagation();
                      setActiveHotspot(activeHotspot === `${room.id}-${hotspot.id}` ? null : `${room.id}-${hotspot.id}`);
                    }}
                  >
                    {/* Pulsating Circle */}
                    <div className="relative">
                      <div className="w-10 h-10 bg-red-500/90 rounded-full flex items-center justify-center animate-pulse-slow">
                        <i className="ri-add-line text-white text-xl"></i>
                      </div>
                      <div className="absolute inset-0 w-10 h-10 bg-red-500/70 rounded-full animate-ping-slow"></div>
                    </div>

                    {/* Product Card */}
                    {activeHotspot === `${room.id}-${hotspot.id}` && (
                      <div
                        className="absolute bg-white rounded-xl shadow-2xl p-4 w-64"
                        style={{
                          left: hotspot.x > 50 ? 'auto' : '50%',
                          right: hotspot.x > 50 ? '50%' : 'auto',
                          top: '50%',
                          transform: hotspot.x > 50 ? 'translate(50%, -50%)' : 'translate(-50%, -50%)',
                          zIndex: 100
                        }}
                        onClick={(e) => e.stopPropagation()}
                      >
                        {hotspot.product && (
                          <>
                            <div className="relative mb-3">
                              {hotspot.product.image || hotspot.product.image_thumb ? (
                                <img
                                  src={hotspot.product.image || hotspot.product.image_thumb || ''}
                                  alt={hotspot.product.name}
                                  className="w-full h-48 object-cover object-top rounded-lg cursor-pointer"
                                  onClick={() => goToProduct(hotspot.product!.id, hotspot.product!.full_path)}
                                />
                              ) : (
                                <div className="w-full h-48 flex items-center justify-center rounded-lg cursor-pointer" onClick={() => goToProduct(hotspot.product!.id, hotspot.product!.full_path)}>
                                  <i className="ri-image-line text-3xl text-gray-400"></i>
                                </div>
                              )}
                              <div className="absolute top-2 right-2 flex gap-2">
                                <button
                                  onClick={() => toggleFavorite(hotspot.product!.id)}
                                  className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 transition-colors cursor-pointer"
                                >
                                  <i className={`${favorites.includes(hotspot.product!.id) ? 'ri-heart-fill text-red-500' : 'ri-heart-line text-gray-600'}`}></i>
                                </button>
                                <button
                                  onClick={(e) => {
                                    e.stopPropagation();
                                  }}
                                  className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 transition-colors cursor-pointer"
                                >
                                  <i className="ri-scales-3-line text-gray-600"></i>
                                </button>
                              </div>
                            </div>
                            <h3
                              className="font-bold text-lg mb-2 cursor-pointer hover:text-red-500 transition-colors"
                              onClick={() => goToProduct(hotspot.product!.id, hotspot.product!.full_path)}
                            >
                              {hotspot.product.name}
                            </h3>
                            <p className="text-red-500 font-bold text-xl mb-3">{hotspot.product.price.toLocaleString()} ₽</p>

                            <div className="flex gap-2">
                              {isVariantParent(hotspot.product) ? (
                                <button
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    goToProduct(hotspot.product!.id, hotspot.product!.full_path);
                                  }}
                                  className="py-2 rounded-lg font-medium transition-all w-full bg-red-500 text-white hover:bg-red-600 cursor-pointer"
                                >
                                  Выбрать
                                </button>
                              ) : (
                                <>
                                  <button
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      addToCart(hotspot.product!);
                                    }}
                                    disabled={isAddingToCart[hotspot.product!.id]}
                                    className={`py-2 rounded-lg font-medium transition-all whitespace-nowrap cursor-pointer ${
                                      addedToCart.includes(hotspot.product!.id)
                                        ? 'bg-red-500 text-white hover:bg-red-600 flex-shrink-0'
                                        : 'bg-red-500 text-white hover:bg-red-600 w-full'
                                    } ${isAddingToCart[hotspot.product!.id] ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    style={addedToCart.includes(hotspot.product!.id) ? { width: '120px' } : {}}
                                  >
                                    {isAddingToCart[hotspot.product!.id]
                                      ? 'Добавление...'
                                      : 'Добавить'}
                                  </button>

                                  {addedToCart.includes(hotspot.product!.id) && (
                                    <div className="flex items-center gap-3 flex-1 rounded-lg px-2 animate-[fadeIn_0.3s_ease-in-out]">
                                      <button
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          updateQuantity(hotspot.product!.id, -1);
                                        }}
                                        className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors"
                                      >
                                        <i className="ri-subtract-line"></i>
                                      </button>
                                      <span className="text-lg font-bold flex-1 text-center">{quantities[hotspot.product!.id] || 1}</span>
                                      <button
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          updateQuantity(hotspot.product!.id, 1);
                                        }}
                                        className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors"
                                      >
                                        <i className="ri-add-line"></i>
                                      </button>
                                    </div>
                                  )}
                                </>
                              )}
                            </div>
                          </>
                        )}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>

        {/* Hint */}
        <div className="text-center mt-8">
          <p className="text-gray-500 text-sm">
            <i className="ri-information-line mr-1"></i>
            Кликните на красные точки, чтобы увидеть товары
          </p>
        </div>
      </div>

      {/* Overlay to close cards */}
      {activeHotspot && (
        <div
          className="fixed inset-0 z-40"
          onClick={() => setActiveHotspot(null)}
        />
      )}
    </section>
  );
}
