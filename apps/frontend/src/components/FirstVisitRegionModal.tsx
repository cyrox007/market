import { useState, useEffect } from 'react';
import { useRegion } from '@/hooks/useRegion';
import LocationModal from './LocationModal';
import { MapPin } from 'lucide-react';

export default function FirstVisitRegionModal() {
  const { region } = useRegion();
  // Маленькое окно-подтверждение автоопределённого региона
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);
  // Полноценная модалка выбора региона
  const [isLocationModalOpen, setIsLocationModalOpen] = useState(false);

  // Показ модалки только при самом первом заходе (guard через localStorage).
  // Без зависимостей и без локального флага, чтобы корректно работать в StrictMode.
  useEffect(() => {
    let firstVisitModalShown: string | null = null;
    try {
      firstVisitModalShown = localStorage.getItem('region_first_visit_modal_shown');
    } catch {
      firstVisitModalShown = null;
    }

    if (firstVisitModalShown) {
      return;
    }

    const timer = setTimeout(() => {
      // На самом первом заходе всегда открываем полноценную модалку выбора региона.
      setIsLocationModalOpen(true);
      try {
        localStorage.setItem('region_first_visit_modal_shown', 'true');
      } catch {
        // Игнорируем проблемы с localStorage
      }
    }, 1500);

    return () => clearTimeout(timer);
  }, []);

  const handleClose = () => {
    setIsConfirmOpen(false);
    setIsLocationModalOpen(false);
    localStorage.setItem('region_first_visit_modal_shown', 'true');
  };

  const handleChooseAnother = () => {
    // Закрываем маленькое окно и открываем большую модалку выбора региона
    setIsConfirmOpen(false);
    setIsLocationModalOpen(true);
  };

  // Слушаем событие выбора региона
  useEffect(() => {
    const handleRegionChange = () => {
      if (isConfirmOpen || isLocationModalOpen) {
        // После выбора региона закрываем модал
        setTimeout(() => {
          setIsConfirmOpen(false);
          setIsLocationModalOpen(false);
          localStorage.setItem('region_first_visit_modal_shown', 'true');
        }, 300);
      }
    };

    window.addEventListener('region-changed', handleRegionChange);
    return () => {
      window.removeEventListener('region-changed', handleRegionChange);
    };
  }, [isConfirmOpen, isLocationModalOpen]);

  if (!isConfirmOpen && !isLocationModalOpen) return null;

  return (
    <>
      {/* Маленькое окно-подтверждение автоопределённого региона */}
      {isConfirmOpen && region && (
        <div
          className="fixed inset-0 z-40 flex items-start justify-center pt-20 px-4"
          aria-modal="true"
          role="dialog"
        >
          <div className="fixed inset-0 bg-black/30" onClick={handleClose} />
          <div className="relative z-50 bg-white rounded-2xl shadow-lg max-w-sm w-full p-5">
            <div className="flex items-start gap-3">
              <div className="mt-0.5 text-red-600">
                <MapPin className="size-[1em] text-2xl" />
              </div>
              <div className="flex-1">
                <h3 className="text-base font-semibold text-gray-900">Мы определили ваш регион</h3>
                <p className="mt-1 text-sm text-gray-600">
                  <span className="font-medium text-gray-900">{region.name}</span>
                </p>
                <p className="mt-1 text-xs text-gray-500">
                  Это нужно для корректного отображения цен и сроков доставки.
                </p>
                <div className="mt-4 flex gap-2">
                  <button
                    type="button"
                    onClick={handleClose}
                    className="inline-flex justify-center px-3 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors flex-1"
                  >
                    Всё верно
                  </button>
                  <button
                    type="button"
                    onClick={handleChooseAnother}
                    className="inline-flex justify-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors flex-1"
                  >
                    Выбрать другой
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Полноценная модалка выбора региона для первого визита */}
      <LocationModal isOpen={isLocationModalOpen} onClose={handleClose} isFirstVisit={true} />
    </>
  );
}
