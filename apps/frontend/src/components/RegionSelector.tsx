'use client';

import { useState, useEffect } from 'react';
import { useRegion } from '@/hooks/useRegion';
import LocationModal from './LocationModal';

export default function RegionSelector() {
  const { region, loading } = useRegion();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [wasAutoDetected, setWasAutoDetected] = useState(false);
  const [isHydrated, setIsHydrated] = useState(false);

  useEffect(() => {
    setIsHydrated(true);
  }, []);

  // Проверяем, был ли регион автоматически определен
  useEffect(() => {
    const autoDetected = localStorage.getItem('region_auto_detected') === 'true';
    const bannerDismissed = localStorage.getItem('region_banner_dismissed') === 'true';
    setWasAutoDetected(autoDetected && !bannerDismissed);
  }, [region]);

  // Слушаем изменения региона для обновления компонента
  useEffect(() => {
    const handleRegionChange = () => {
      // Компонент автоматически обновится через useRegion hook
      const autoDetected = localStorage.getItem('region_auto_detected') === 'true';
      const bannerDismissed = localStorage.getItem('region_banner_dismissed') === 'true';
      setWasAutoDetected(autoDetected && !bannerDismissed);
    };

    window.addEventListener('region-changed', handleRegionChange);
    return () => {
      window.removeEventListener('region-changed', handleRegionChange);
    };
  }, []);

  // Не блокируем кнопку даже во время загрузки - пользователь может кликнуть и выбрать регион
  // Показываем состояние загрузки только если регион еще не определен
  if (loading && !region) {
    return (
      <button
        type="button"
        onClick={() => setIsModalOpen(true)}
        className="relative flex items-center gap-1.5 text-xs md:text-sm text-gray-600 hover:text-gray-900 transition-colors group"
        title="Выберите регион доставки для корректного отображения цен и сроков доставки"
      >
        <i className="ri-map-pin-line text-base"></i>
        <span className="hidden lg:inline">Загрузка...</span>
        <span className="lg:hidden">...</span>
        {/* Tooltip */}
        <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all pointer-events-none whitespace-nowrap z-50">
          Выберите регион доставки
          <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
        </div>
      </button>
    );
  }

  const regionName = isHydrated && region ? region.name : 'Выбрать город';
  const regionTitle = isHydrated && region ? `Локация доставки: ${region.name}` : 'Выберите регион доставки';
  const showAutoDetectedBadge = isHydrated && wasAutoDetected && region;

  return (
    <>
      <button
        type="button"
        onClick={() => setIsModalOpen(true)}
        className="relative flex w-full min-w-0 items-center gap-1.5 text-xs md:text-sm text-gray-600 hover:text-red-600 transition-colors group"
        title={regionTitle}
      >
        <i className={`ri-map-pin-line text-base ${showAutoDetectedBadge ? 'text-red-600' : ''}`}></i>
        <span className="hidden min-w-0 flex-1 truncate font-medium md:inline">
          {regionName}
        </span>
        <span className="min-w-0 flex-1 truncate font-medium md:hidden">
          {regionName}
        </span>
        {showAutoDetectedBadge && (
          <span className="hidden lg:inline text-[10px] text-red-600 bg-red-100 px-1.5 py-0.5 rounded-full flex-shrink-0">
            Авто
          </span>
        )}
        <i className="ri-arrow-down-s-line text-gray-400 group-hover:text-gray-600 text-xs md:text-sm transition-colors flex-shrink-0"></i>

        {/* Tooltip - показываем только если регион не выбран */}
        {!region && (
          <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all pointer-events-none whitespace-nowrap z-50">
            Выберите регион доставки для корректного отображения цен и сроков доставки
            <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
          </div>
        )}
      </button>

      <LocationModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />
    </>
  );
}
