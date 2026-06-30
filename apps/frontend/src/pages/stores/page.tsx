import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';
import type { Store } from '../../lib/api';

export default function Stores() {
  const [selectedCity, setSelectedCity] = useState('Все города');
  const [stores, setStores] = useState<Store[]>([]);
  const [cities, setCities] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadData = async () => {
      try {
        setIsLoading(true);
        setError(null);

        // Загружаем список городов
        const citiesData = await api.stores.cities();
        setCities(['Все города', ...citiesData.cities]);

        // Загружаем магазины
        const storesData = await api.stores.list({
          city: selectedCity === 'Все города' ? undefined : selectedCity || undefined
        });
        setStores(storesData.data);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Ошибка загрузки данных');
        console.error('Failed to load stores:', err);
      } finally {
        setIsLoading(false);
      }
    };

    loadData();
  }, [selectedCity]);

  const handleCityChange = (city: string) => {
    setSelectedCity(city);
  };

  const filteredStores = stores;

  if (isLoading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-12">
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
        <div className="max-w-7xl mx-auto px-4 py-12">
          <div className="text-center py-16">
            <p className="text-red-600 mb-4">{error}</p>
            <button
              onClick={() => window.location.reload()}
              className="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700"
            >
              Обновить страницу
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Получаем код для Яндекс карты или формируем URL для Google Maps
  const getMapContent = () => {
    if (filteredStores.length === 0) return null;

    // Если есть Яндекс карта у первого магазина, используем её
    const firstStore = filteredStores[0];
    if (firstStore.yandex_map) {
      // Если это iframe код, извлекаем src
      const iframeMatch = firstStore.yandex_map.match(/src=["']([^"']+)["']/);
      if (iframeMatch) {
        return { type: 'yandex', src: iframeMatch[1] };
      }
      // Если это прямая ссылка
      if (firstStore.yandex_map.startsWith('http')) {
        return { type: 'yandex', src: firstStore.yandex_map };
      }
      // Если это полный HTML код iframe
      return { type: 'yandex', html: firstStore.yandex_map };
    }

    // Иначе используем Google Maps
    if (firstStore.coordinates_array) {
      return {
        type: 'google',
        src: `https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2000!2d${firstStore.coordinates_array.lng}!3d${firstStore.coordinates_array.lat}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2z${firstStore.coordinates_array.lat}%2C${firstStore.coordinates_array.lng}!5e0!3m2!1sru!2sru!4v1234567890`
      };
    }

    return {
      type: 'google',
      src: 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2245.4926492354165!2d37.61842315!3d55.75124425!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46b54a50b315e573%3A0xa886bf5a3d9b2e68!2z0JzQvtGB0LrQstCw!5e0!3m2!1sru!2sru!4v1234567890'
    };
  };

  const getRouteUrl = (store: Store) => {
    if (store.coordinates_array) {
      return `https://www.google.com/maps/dir/?api=1&destination=${store.coordinates_array.lat},${store.coordinates_array.lng}`;
    }
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(store.address)}`;
  };

  return (
    <div className="min-h-screen bg-white">

      {/* Hero */}
      <div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-20">
        <div className="max-w-7xl mx-auto px-4 text-center">
          <h1 className="text-5xl font-bold mb-4">Наши магазины</h1>
          <p className="text-xl opacity-90">Посетите наши салоны и выберите мебель вживую</p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-12">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
          <Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
          <i className="ri-arrow-right-s-line text-gray-400"></i>
          <span className="text-gray-900">Магазины</span>
        </div>

        {/* Filter */}
        <div className="flex items-center justify-between mb-8 flex-wrap gap-4">
          <h2 className="text-2xl font-bold">Найдено магазинов: {filteredStores.length}</h2>
          <div className="flex items-center gap-4">
            <span className="text-gray-600">Город:</span>
            <select
              value={selectedCity}
              onChange={(e) => handleCityChange(e.target.value)}
              className="px-4 py-2 border border-gray-300 rounded-lg pr-8"
            >
              {cities.map((city) => (
                <option key={city} value={city}>{city}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Map */}
        {filteredStores.length > 0 && (() => {
          const mapContent = getMapContent();
          if (!mapContent) return null;

          return (
            <div className="bg-gray-100 rounded-2xl overflow-hidden mb-12 h-96">
              {mapContent.type === 'yandex' && mapContent.html ? (
                <div dangerouslySetInnerHTML={{ __html: mapContent.html }} />
              ) : (
                <iframe
                  src={mapContent.src}
                  width="100%"
                  height="100%"
                  style={{ border: 0 }}
                  allowFullScreen
                  loading="lazy"
                ></iframe>
              )}
            </div>
          );
        })()}

        {/* Stores Grid */}
        {filteredStores.length === 0 ? (
          <div className="text-center py-16">
            <div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i className="ri-store-line text-6xl text-gray-400"></i>
            </div>
            <h2 className="text-2xl font-bold mb-4">Магазины не найдены</h2>
            <p className="text-gray-600">В выбранном городе пока нет магазинов</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {filteredStores.map((store) => (
              <div key={store.id} className="bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-shadow">
                <div className="h-48 overflow-hidden bg-gray-100">
                  {store.image ? (
                    <img src={store.image} alt={store.name} className="w-full h-full object-cover object-top" />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-gray-400">
                      <i className="ri-store-line text-6xl"></i>
                    </div>
                  )}
                </div>
                <div className="p-6">
                  <h3 className="text-xl font-bold mb-2">{store.name}</h3>
                  <p className="text-gray-600 mb-4">{store.city}</p>

                  <div className="space-y-3 mb-6">
                    <div className="flex items-start gap-3">
                      <div className="w-6 h-6 flex items-center justify-center flex-shrink-0">
                        <i className="ri-map-pin-line text-red-600"></i>
                      </div>
                      <p className="text-gray-700">{store.address}</p>
                    </div>
                    <div className="flex items-start gap-3">
                      <div className="w-6 h-6 flex items-center justify-center flex-shrink-0">
                        <i className="ri-phone-line text-red-600"></i>
                      </div>
                      <a href={`tel:${store.phone.replace(/\s/g, '')}`} className="text-gray-700 hover:text-red-600">
                        {store.phone}
                      </a>
                    </div>
                    {store.hours && (
                      <div className="flex items-start gap-3">
                        <div className="w-6 h-6 flex items-center justify-center flex-shrink-0">
                          <i className="ri-time-line text-red-600"></i>
                        </div>
                        <p className="text-gray-700">{store.hours}</p>
                      </div>
                    )}
                  </div>

                  <div className="flex gap-3">
                    <a
                      href={getRouteUrl(store)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex-1 bg-red-600 text-white py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap text-center"
                    >
                      Построить маршрут
                    </a>
                    <button
                      onClick={() => {
                        const url = window.location.href;
                        navigator.clipboard.writeText(url);
                        alert('Ссылка скопирована!');
                      }}
                      className="w-12 h-12 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 cursor-pointer"
                    >
                      <i className="ri-share-line text-xl"></i>
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Benefits */}
        <div className="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8">
          <div className="text-center p-8 bg-red-50 rounded-2xl">
            <div className="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="ri-eye-line text-3xl text-white"></i>
            </div>
            <h3 className="text-xl font-bold mb-2">Посмотрите вживую</h3>
            <p className="text-gray-600">Оцените качество материалов и удобство мебели в наших салонах</p>
          </div>
          <div className="text-center p-8 bg-yellow-50 rounded-2xl">
            <div className="w-16 h-16 bg-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="ri-user-smile-line text-3xl text-white"></i>
            </div>
            <h3 className="text-xl font-bold mb-2">Консультация</h3>
            <p className="text-gray-600">Наши специалисты помогут подобрать идеальную мебель для вас</p>
          </div>
          <div className="text-center p-8 bg-green-50 rounded-2xl">
            <div className="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="ri-gift-line text-3xl text-white"></i>
            </div>
            <h3 className="text-xl font-bold mb-2">Специальные предложения</h3>
            <p className="text-gray-600">Эксклюзивные скидки для посетителей салонов</p>
          </div>
        </div>
      </div>

    </div>
  );
}
