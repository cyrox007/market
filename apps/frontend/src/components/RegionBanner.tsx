import { useState, useEffect } from 'react';
import { useRegion } from '@/hooks/useRegion';
import LocationModal from './LocationModal';
import { MapPin, X } from 'lucide-react';

export default function RegionBanner() {
  const { region, loading } = useRegion();
  const [isVisible, setIsVisible] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Показываем баннер только если регион был автоматически определен и пользователь еще не закрывал его
  useEffect(() => {
    const bannerDismissed = localStorage.getItem('region_banner_dismissed');
    const wasAutoDetected = localStorage.getItem('region_auto_detected');

    // Показываем баннер если:
    // 1. Регион был автоматически определен
    // 2. Пользователь еще не закрывал баннер
    // 3. Есть регион
    if (wasAutoDetected === 'true' && !bannerDismissed && region) {
      setIsVisible(true);
    }
  }, [region]);

  const handleDismiss = () => {
    setIsVisible(false);
    localStorage.setItem('region_banner_dismissed', 'true');
  };

  const handleChangeRegion = () => {
    setIsModalOpen(true);
  };

  if (!isVisible || loading || !region) {
    return null;
  }

  return (
    <>
      <div className="bg-[#F5F5F5] border-b border-gray-200 text-xs md:text-sm">
        <div className="max-w-[1280px] mx-auto px-4 py-1.5">
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-2 flex-1 min-w-0">
              <MapPin className="size-[1em] text-red-600 flex-shrink-0 text-base" />
              <span className="text-gray-600 truncate">
                Ваш регион: <span className="font-medium text-gray-900">{region.name}</span>
              </span>
              <button
                type="button"
                onClick={handleChangeRegion}
                className="text-red-600 hover:text-red-700 font-medium ml-1 flex-shrink-0 transition-colors"
              >
                Изменить
              </button>
            </div>
            <button
              type="button"
              onClick={handleDismiss}
              className="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0 p-1"
              aria-label="Закрыть"
            >
              <X className="size-[1em] text-lg" />
            </button>
          </div>
        </div>
      </div>

      <LocationModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />
    </>
  );
}
