'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useRegion } from '../hooks/useRegion';
import { api } from '../lib/api';
import type { ShippingLocationTree, ShippingLocation } from '../lib/api';
import { Check, ChevronDown, Search, TriangleAlert, X } from 'lucide-react';

interface LocationModalProps {
  isOpen: boolean;
  onClose: () => void;
  isFirstVisit?: boolean;
}

export default function LocationModal({ isOpen, onClose, isFirstVisit = false }: LocationModalProps) {
  const { region, selectRegion } = useRegion();
  const [tree, setTree] = useState<ShippingLocationTree[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [isGeoDetecting, setIsGeoDetecting] = useState(false);
  const [expandedDistricts, setExpandedDistricts] = useState<Set<number>>(new Set());
  const [expandedRegions, setExpandedRegions] = useState<Set<number>>(new Set());

  // Загружаем дерево локаций только при открытии модалки
  useEffect(() => {
    if (isOpen && tree.length === 0 && !loading) {
      loadTree();
    }
  }, [isOpen]);

  const loadTree = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.regions.tree();
      
      if (!response || !response.data) {
        throw new Error('Неверный формат ответа от сервера');
      }
      
      const locations = Array.isArray(response.data) ? response.data : [];
      setTree(locations);
      
      // Автоматически раскрываем выбранный регион
      if (region) {
        locations.forEach((district) => {
          district.children?.forEach((reg) => {
            if (reg.id === region.id || reg.children?.some(c => c.id === region.id)) {
              setExpandedDistricts(prev => new Set(prev).add(district.id));
              setExpandedRegions(prev => new Set(prev).add(reg.id));
            }
          });
        });
      }
    } catch (err: any) {
      console.error('Failed to load locations tree:', err);
      let errorMessage = 'Не удалось загрузить список локаций';
      if (err?.status === 404) {
        errorMessage = 'Маршрут API не найден. Проверьте настройки сервера.';
      } else if (err?.message) {
        errorMessage = err.message;
      }
      setError(errorMessage);
      setTree([]);
    } finally {
      setLoading(false);
    }
  };

  // Определение города пользователя по координатам браузера
  const reverseGeocodeCity = useCallback(async (lat: number, lon: number): Promise<string | null> => {
    try {
      const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}&accept-language=ru`;
      const response = await fetch(url, {
        headers: {
          // Помогаем сервису понять источник запросов
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        return null;
      }

      const data: any = await response.json();
      if (!data?.address) return null;

      const address = data.address;
      // Пытаемся взять наиболее релевантное поле
      return (
        address.city ||
        address.town ||
        address.village ||
        address.hamlet ||
        address.municipality ||
        address.county ||
        null
      );
    } catch {
      return null;
    }
  }, []);

  // Преобразуем ShippingLocationTree в ShippingLocation для сохранения
  const convertToShippingLocation = (treeItem: ShippingLocationTree): ShippingLocation => {
    return {
      id: treeItem.id,
      name: treeItem.name,
      slug: treeItem.slug || '',
      type: treeItem.type,
      parent_id: treeItem.parent_id || null,
      is_active: true,
    } as ShippingLocation;
  };

  const handleSelectLocation = useCallback((location: ShippingLocationTree) => {
    try {
      const shippingLocation = convertToShippingLocation(location);
      // Выбираем регион
      selectRegion(shippingLocation);
      
      // Закрываем модалку, данные обновятся через region-changed и SWR-ключи.
      onClose();
      setSearchQuery('');
    } catch {
      alert('Ошибка при выборе локации. Пожалуйста, попробуйте снова.');
    }
  }, [selectRegion, onClose]);

  const toggleDistrict = useCallback((districtId: number) => {
    setExpandedDistricts(prev => {
      const newSet = new Set(prev);
      if (newSet.has(districtId)) {
        newSet.delete(districtId);
      } else {
        newSet.add(districtId);
      }
      return newSet;
    });
  }, []);

  const toggleRegion = useCallback((regionId: number) => {
    setExpandedRegions(prev => {
      const newSet = new Set(prev);
      if (newSet.has(regionId)) {
        newSet.delete(regionId);
      } else {
        newSet.add(regionId);
      }
      return newSet;
    });
  }, []);

  // Фильтрация по поисковому запросу
  const filteredTree = useMemo(() => {
    if (!searchQuery.trim()) {
      return tree;
    }

    const query = searchQuery.toLowerCase();
    const filtered: ShippingLocationTree[] = [];

    tree.forEach((district) => {
      const matchingRegions: ShippingLocationTree[] = [];

      district.children?.forEach((reg) => {
        const matchingLocalities: ShippingLocationTree[] = [];
        let regionMatches = reg.name.toLowerCase().includes(query);

        reg.children?.forEach((locality) => {
          if (locality.name.toLowerCase().includes(query)) {
            matchingLocalities.push(locality);
            regionMatches = true;
          }
        });

        if (regionMatches || matchingLocalities.length > 0) {
          matchingRegions.push({
            ...reg,
            children: matchingLocalities.length > 0 ? matchingLocalities : reg.children,
          });
        }
      });

      if (district.name.toLowerCase().includes(query) || matchingRegions.length > 0) {
        filtered.push({
          ...district,
          children: matchingRegions,
        });
      }
    });

    return filtered;
  }, [tree, searchQuery]);

  // Автоопределение региона при первом визите по геолокации браузера
  useEffect(() => {
    // Работает только при первом визите и открытой модалке,
    // если регион еще не выбран пользователем
    if (!isOpen || !isFirstVisit || region || isGeoDetecting) {
      return;
    }

    if (typeof window === 'undefined' || !('geolocation' in navigator)) {
      return;
    }

    setIsGeoDetecting(true);

    navigator.geolocation.getCurrentPosition(
      async (position) => {
        try {
          const { latitude, longitude } = position.coords;
          const city = await reverseGeocodeCity(latitude, longitude);

          if (!city) {
            return;
          }

          // Заполняем поиск автоматически, чтобы пользователь сразу видел свой город/регион
          setSearchQuery((prev) => prev || city);

          // Дополнительно пробуем автоматически подобрать регион через бэкенд,
          // используя название города. Это улучшает точность по сравнению с IP.
          try {
            const detected = await api.regions.detect({ city });
            if (detected?.region) {
              selectRegion(detected.region as ShippingLocation);
              // Помечаем регион как автоматически определенный
              try {
                localStorage.setItem('region_auto_detected', 'true');
              } catch {
                // Игнорируем проблемы с localStorage
              }
            }
          } catch {
            // ignore
          }
        } finally {
          setIsGeoDetecting(false);
        }
      },
      () => {
        setIsGeoDetecting(false);
      },
      {
        enableHighAccuracy: false,
        timeout: 8000,
        maximumAge: 5 * 60 * 1000,
      },
    );
  }, [isOpen, isFirstVisit, region, reverseGeocodeCity, selectRegion, isGeoDetecting]);

  // Автоматически раскрываем аккордеон при поиске
  useEffect(() => {
    if (!searchQuery.trim() || filteredTree.length === 0) {
      return;
    }

    const districtsToExpand = new Set<number>();
    const regionsToExpand = new Set<number>();

    // Находим все округа и регионы, которые содержат найденные элементы
    filteredTree.forEach((district) => {
      if (district.children && district.children.length > 0) {
        districtsToExpand.add(district.id);

        district.children.forEach((reg) => {
          // Если регион сам соответствует поиску или содержит найденные населенные пункты
          if (reg.children && reg.children.length > 0) {
            regionsToExpand.add(reg.id);
          }
        });
      }
    });

    // Раскрываем найденные элементы
    setExpandedDistricts(prev => {
      const newSet = new Set(prev);
      districtsToExpand.forEach(id => newSet.add(id));
      return newSet;
    });

    setExpandedRegions(prev => {
      const newSet = new Set(prev);
      regionsToExpand.forEach(id => newSet.add(id));
      return newSet;
    });

    // Прокручиваем до первого найденного элемента после небольшой задержки
    // (чтобы дать время аккордеону раскрыться)
    const scrollTimer = setTimeout(() => {
      const modalContent = document.querySelector('[role="dialog"] .bg-white.rounded-2xl');
      if (modalContent) {
        // Находим первый найденный элемент (подсвеченный желтым или красным)
        const firstMatch = modalContent.querySelector('[data-location-id].bg-yellow-50, [data-location-id].bg-red-50') as HTMLElement;
        if (firstMatch) {
          firstMatch.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
    }, 150);

    return () => clearTimeout(scrollTimer);
  }, [searchQuery, filteredTree]);

  // Обработка закрытия по Escape (только если не первый визит)
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen && !isFirstVisit) {
        onClose();
      }
    };
    
    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
      document.body.style.overflow = 'hidden';
    }
    
    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = '';
    };
  }, [isOpen, onClose, isFirstVisit]);

  if (!isOpen) return null;

  const displayTree = searchQuery.trim() ? filteredTree : tree;
  const isFederalDistricts = displayTree.length > 0 && displayTree[0]?.type === 'federal_district';

  return (
    <div 
      className="fixed inset-0 z-50 overflow-y-auto" 
      aria-labelledby="modal-title" 
      role="dialog" 
      aria-modal="true"
      onClick={(e) => {
        if (e.target === e.currentTarget && !isFirstVisit) {
          onClose();
        }
      }}
    >
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/50 transition-opacity"></div>

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div 
          className="bg-white rounded-2xl p-8 max-w-2xl w-full max-h-[90vh] overflow-y-auto relative"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between mb-6">
            <div>
              <h3 className="text-2xl font-bold">Выберите город доставки</h3>
              {isFirstVisit && (
                <p className="text-sm text-gray-600 mt-1">
                  Выберите ваш город для корректного отображения цен и сроков доставки
                </p>
              )}
            </div>
            {!isFirstVisit && (
              <button
                onClick={onClose}
                className="text-gray-400 hover:text-gray-600 transition-colors"
                aria-label="Закрыть"
              >
                <X className="size-[1em] text-2xl" />
              </button>
            )}
          </div>

          {/* Search */}
          <div className="mb-6">
            <div className="relative">
              <input
                type="text"
                placeholder="Поиск по городу или региону..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100"
                autoFocus
              />
              <Search className="size-[1em] absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg" />
            </div>
          </div>

          {/* Content */}
          <div className="mb-6">
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <div className="w-8 h-8 border-2 border-gray-300 border-t-red-600 rounded-full animate-spin"></div>
                <span className="ml-3 text-gray-600">Загрузка локаций...</span>
              </div>
            ) : error ? (
              <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                <TriangleAlert className="size-[1em] text-xl text-red-600 mt-0.5" />
                <div className="flex-1">
                  <p className="text-red-600 text-sm mb-4">{error}</p>
                  <button
                    onClick={loadTree}
                    className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                  >
                    Попробовать снова
                  </button>
                </div>
              </div>
            ) : displayTree.length === 0 ? (
              <div className="text-center py-12">
                <div className="text-gray-500 mb-4">
                  {searchQuery.trim() 
                    ? `По запросу "${searchQuery}" ничего не найдено`
                    : 'Локации не найдены. Проверьте, что в системе есть активные локации доставки.'}
                </div>
                {searchQuery.trim() ? (
                  <button
                    onClick={() => setSearchQuery('')}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    Очистить поиск
                  </button>
                ) : (
                  <button
                    onClick={loadTree}
                    className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                  >
                    Попробовать снова
                  </button>
                )}
              </div>
            ) : (
              <div className="space-y-2">
                {displayTree.map((district) => {
                  const isDistrictExpanded = expandedDistricts.has(district.id);
                  const hasRegions = district.children && district.children.length > 0;

                  return (
                    <div key={district.id} className="border border-gray-200 rounded-lg overflow-hidden">
                      {/* Федеральный округ */}
                      {isFederalDistricts ? (
                        <button
                          onClick={() => toggleDistrict(district.id)}
                          className="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors flex items-center justify-between"
                        >
                          <span className="font-semibold text-gray-900">{district.name}</span>
                          <ChevronDown className={`size-[1em] text-gray-500 text-lg transition-transform ${isDistrictExpanded ? 'transform rotate-180' : ''}`} />
                        </button>
                      ) : null}

                      {/* Регионы */}
                      {(isDistrictExpanded || !isFederalDistricts) && hasRegions && (
                        <div className="bg-white">
                          {district.children!.map((reg) => {
                            const isRegionExpanded = expandedRegions.has(reg.id);
                            const hasLocalities = reg.children && reg.children.length > 0;
                            const isRegionSelected = region?.id === reg.id;
                            const matchesSearch = searchQuery.trim() && 
                              reg.name.toLowerCase().includes(searchQuery.toLowerCase());

                            return (
                              <div key={reg.id} className="border-t border-gray-200 first:border-t-0">
                                {/* Регион */}
                                <div className="flex items-center">
                                  {hasLocalities ? (
                                    <button
                                      onClick={() => toggleRegion(reg.id)}
                                      className={`flex-1 text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center justify-between ${
                                        matchesSearch ? 'bg-yellow-50' : ''
                                      }`}
                                      data-location-id={reg.id}
                                    >
                                      <span className={`text-sm ${isRegionSelected ? 'font-medium text-red-600' : matchesSearch ? 'font-medium text-gray-900' : 'text-gray-700'}`}>
                                        {reg.name}
                                      </span>
                                      <ChevronDown className={`size-[1em] text-gray-400 text-base transition-transform ${isRegionExpanded ? 'transform rotate-180' : ''}`} />
                                    </button>
                                  ) : (
                                    <button
                                      onClick={() => handleSelectLocation(reg)}
                                      className={`flex-1 text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center justify-between ${
                                        isRegionSelected ? 'bg-red-50' : matchesSearch ? 'bg-yellow-50' : ''
                                      }`}
                                      data-location-id={reg.id}
                                    >
                                      <span className={`text-sm ${isRegionSelected ? 'font-medium text-red-600' : matchesSearch ? 'font-medium text-gray-900' : 'text-gray-700'}`}>
                                        {reg.name}
                                      </span>
                                      {isRegionSelected && (
                                        <Check className="size-[1em] text-red-600 text-lg" />
                                      )}
                                    </button>
                                  )}
                                </div>

                                {/* Города */}
                                {isRegionExpanded && hasLocalities && (
                                  <div className="bg-gray-50 pl-8">
                                    {reg.children!.map((locality) => {
                                      const isLocalitySelected = region?.id === locality.id;
                                      const matchesSearch = searchQuery.trim() && 
                                        locality.name.toLowerCase().includes(searchQuery.toLowerCase());
                                      return (
                                        <button
                                          key={locality.id}
                                          onClick={() => handleSelectLocation(locality)}
                                          className={`w-full text-left px-4 py-2.5 hover:bg-gray-100 transition-colors flex items-center justify-between ${
                                            isLocalitySelected ? 'bg-red-50' : matchesSearch ? 'bg-yellow-50' : ''
                                          }`}
                                          data-location-id={locality.id}
                                        >
                                          <span className={`text-sm ${isLocalitySelected ? 'font-medium text-red-600' : matchesSearch ? 'font-medium text-gray-900' : 'text-gray-700'}`}>
                                            {locality.name}
                                          </span>
                                          {isLocalitySelected && (
                                            <Check className="size-[1em] text-red-600 text-lg" />
                                          )}
                                        </button>
                                      );
                                    })}
                                  </div>
                                )}
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Footer */}
          {region && (
            <div className="mt-6 pt-6 border-t border-gray-200">
              <div className="text-sm text-gray-600">
                Текущий выбор: <span className="font-medium text-gray-900">{region.name}</span>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
