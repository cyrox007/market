import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';
import { useCartActions } from '../../hooks/useCartActions';
import { getCartQuantityForProduct, isVariableParent } from '../../utils/cartProduct';
import { useCounters } from '../../hooks/useCounters';
import type { Product } from '../../lib/api';

export default function Compare() {
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const { addProductToCart, cart } = useCartActions();
  const { refreshCompareCount } = useCounters();

  useEffect(() => {
    const loadCompareList = async () => {
      try {
        setIsLoading(true);
        const response = await api.compare.list();
        setProducts(response.products || []);
      } catch {
        // ignore
      } finally {
        setIsLoading(false);
      }
    };

    loadCompareList();
  }, []);

  const removeProduct = async (id: number) => {
    try {
      await api.compare.remove(id);
      setProducts(prev => prev.filter(p => p.id !== id));
      // Небольшая задержка, чтобы дать время API обновиться
      await new Promise(resolve => setTimeout(resolve, 100));
      await refreshCompareCount();
    } catch {
      // ignore
    }
  };

  const handleAddToCart = async (product: Product) => {
    if (isVariableParent(product)) return;
    await addProductToCart(product, 1);
  };

  // Получаем все уникальные характеристики из всех товаров и их названия
  const getAllSpecifications = () => {
    const allSpecs = new Set<string>();
    const specNamesMap: Record<string, string> = {};

    products.forEach(product => {
      if (product.specifications) {
        Object.keys(product.specifications).forEach(key => {
          allSpecs.add(key);
          // Используем название из specification_names, если есть, иначе преобразуем slug
          if ((product as any).specification_names?.[key]) {
            specNamesMap[key] = (product as any).specification_names[key];
          } else if (!specNamesMap[key]) {
            // Преобразуем slug в читаемое название
            specNamesMap[key] = key.replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
          }
        });
      }
    });

    return { keys: Array.from(allSpecs), names: specNamesMap };
  };

  const { keys: allSpecifications, names: specNames } = getAllSpecifications();

  return (
    <div className="min-h-screen bg-white">

      <div className="max-w-7xl mx-auto px-4 py-6">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-sm mb-4">
          <Link to="/" className="text-gray-600 hover:text-red-600 cursor-pointer">Главная</Link>
          <i className="ri-arrow-right-s-line text-gray-400"></i>
          <span className="text-gray-900">Сравнение товаров</span>
        </div>

        {isLoading ? (
          <div className="text-center py-20">
            <div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i className="ri-loader-4-line text-6xl text-gray-400 animate-spin"></i>
            </div>
            <h2 className="text-2xl font-bold mb-3">Загрузка...</h2>
          </div>
        ) : products.length === 0 ? (
          <div className="text-center py-20">
            <div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i className="ri-scales-3-line text-6xl text-gray-400"></i>
            </div>
            <h2 className="text-2xl font-bold mb-3">Список сравнения пуст</h2>
            <p className="text-gray-600 mb-6">Добавляйте товары для сравнения характеристик</p>
            <Link to="/catalog" className="inline-block bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap cursor-pointer">
              Перейти в каталог
            </Link>
          </div>
        ) : (
          <div className="border border-gray-200 rounded-2xl overflow-hidden">
            <div className="overflow-x-auto overflow-y-auto max-h-[calc(100vh-200px)]">
              <div className="inline-block min-w-full">
                {/* Products Row - Sticky Top */}
                <div className="flex sticky top-0 bg-white z-20 border-b border-gray-200 shadow-sm">
                  {/* Title cell */}
                  <div className="w-64 flex-shrink-0 sticky left-0 bg-white z-30 border-r border-gray-200 p-4 flex items-center">
                    <div>
                      <h1 className="text-2xl font-bold">Сравнение товаров</h1>
                      <span className="text-sm text-gray-600">{products.length} товаров</span>
                    </div>
                  </div>

                  {/* Product Cards */}
                  {products.map((product) => (
                    <div key={product.id} className="w-64 flex-shrink-0 p-4 border-r border-gray-200 last:border-r-0">
                      <div className="relative">
                        <button
                          onClick={() => removeProduct(product.id)}
                          className="absolute -top-2 -right-2 w-7 h-7 bg-red-600 rounded-full flex items-center justify-center shadow-md hover:bg-red-700 cursor-pointer z-10"
                        >
                          <i className="ri-close-line text-white text-sm"></i>
                        </button>
                        <div className="relative">
                          {product.thumbnail || product.image ? (
                            <img src={product.thumbnail || product.image || ''} alt={product.name} className="w-full h-24 object-cover object-top rounded-lg mb-2" />
                          ) : (
                            <div className="w-full h-24 flex items-center justify-center rounded-lg mb-2">
                              <i className="ri-image-line text-3xl text-gray-400"></i>
                            </div>
                          )}
                          {(!product.in_stock || product.stock === 0) && (
                            <div className="absolute inset-0 bg-black/50 flex items-center justify-center rounded-lg z-20">
                              <span className="bg-white px-2 py-1 rounded text-xs font-medium">Нет в наличии</span>
                            </div>
                          )}
                        </div>
                        <h3 className="font-bold text-sm mb-2 min-h-[40px] line-clamp-2">{product.name}</h3>
                        <div className="flex items-center justify-between gap-2 mb-2">
                          <p className="text-lg font-bold text-red-600">{product.price.toLocaleString()} ₽</p>
                          {isVariableParent(product) ? (
                            <Link
                              to={`/product/${product.slug}`}
                              className="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors whitespace-nowrap bg-red-600 text-white hover:bg-red-700"
                            >
                              Выбрать
                            </Link>
                          ) : (
                          <button
                              onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                if (product.in_stock) {
                                  handleAddToCart(product);
                                }
                              }}
                              disabled={!product.in_stock}
                            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors whitespace-nowrap cursor-pointer ${
                              getCartQuantityForProduct(cart?.items, product) > 0
                                ? 'bg-green-600 text-white hover:bg-green-700'
                                  : product.in_stock
                                  ? 'bg-red-600 text-white hover:bg-red-700'
                                  : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                            }`}
                          >
                              {getCartQuantityForProduct(cart?.items, product) > 0
                                ? `В корзине (${getCartQuantityForProduct(cart?.items, product)})`
                                : product.in_stock
                                  ? 'В корзину'
                                  : 'Недоступно'}
                          </button>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Specifications Rows */}
                {allSpecifications.length > 0 ? (
                  allSpecifications.map((specKey) => (
                  <div key={specKey} className="flex border-b border-gray-200 last:border-b-0 hover:bg-gray-50">
                    {/* Label Column - Sticky Left */}
                    <div className="w-64 flex-shrink-0 sticky left-0 bg-white z-10 border-r border-gray-200 p-4 font-medium text-sm">
                        {specNames[specKey] || specKey.replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                    </div>

                    {/* Values */}
                    {products.map((product) => (
                      <div key={product.id} className="w-64 flex-shrink-0 p-4 border-r border-gray-200 last:border-r-0 text-sm">
                          {product.specifications?.[specKey] || '-'}
                        </div>
                      ))}
                    </div>
                  ))
                ) : (
                  <div className="flex border-b border-gray-200">
                    <div className="w-64 flex-shrink-0 sticky left-0 bg-white z-10 border-r border-gray-200 p-4 font-medium text-sm">
                      Характеристики
                    </div>
                    {products.map((product) => (
                      <div key={product.id} className="w-64 flex-shrink-0 p-4 border-r border-gray-200 last:border-r-0 text-sm text-gray-500">
                        Не указаны
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {products.length > 0 && (
          <div className="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-start gap-3">
            <i className="ri-information-line text-yellow-600 text-xl flex-shrink-0 mt-0.5"></i>
            <div className="text-sm text-gray-700">
              <p className="font-medium mb-1">Подсказка:</p>
              <p>Прокрутите таблицу влево, чтобы увидеть все товары. Прокрутите вниз, чтобы сравнить все характеристики.</p>
            </div>
          </div>
        )}
      </div>

    </div>
  );
}
