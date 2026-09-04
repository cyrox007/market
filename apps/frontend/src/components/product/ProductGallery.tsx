import { useState, useRef, useCallback, useEffect } from 'react';
import { ChevronLeft, ChevronRight, ImageIcon, X, ZoomIn } from 'lucide-react';

export interface ProductGalleryProps {
  images: string[];
  productName: string;
  selectedIndex: number;
  onSelectIndex: (index: number) => void;
}

const SWIPE_THRESHOLD = 50;

export default function ProductGallery({
  images,
  productName,
  selectedIndex,
  onSelectIndex,
}: ProductGalleryProps) {
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [lightboxZoom, setLightboxZoom] = useState(false);
  const [touchStart, setTouchStart] = useState<number | null>(null);
  const [touchDelta, setTouchDelta] = useState(0);
  const lightboxTouchStartX = useRef<number | null>(null);

  const hasMultiple = images.length > 1;
  const currentImage = images[selectedIndex] ?? images[0];

  // При открытии лайтбокса сбрасываем зум
  useEffect(() => {
    if (!lightboxOpen) setLightboxZoom(false);
  }, [lightboxOpen]);

  const goPrev = useCallback(() => {
    if (!hasMultiple) return;
    onSelectIndex(selectedIndex <= 0 ? images.length - 1 : selectedIndex - 1);
  }, [hasMultiple, selectedIndex, images.length, onSelectIndex]);

  const goNext = useCallback(() => {
    if (!hasMultiple) return;
    onSelectIndex(selectedIndex >= images.length - 1 ? 0 : selectedIndex + 1);
  }, [hasMultiple, selectedIndex, images.length, onSelectIndex]);

  const handleMainTouchStart = (e: React.TouchEvent) => {
    setTouchStart(e.touches[0].clientX);
    setTouchDelta(0);
  };

  const handleMainTouchMove = (e: React.TouchEvent) => {
    if (touchStart === null) return;
    setTouchDelta(e.touches[0].clientX - touchStart);
  };

  const handleMainTouchEnd = () => {
    if (touchStart === null) return;
    if (touchDelta < -SWIPE_THRESHOLD) goNext();
    else if (touchDelta > SWIPE_THRESHOLD) goPrev();
    setTouchStart(null);
    setTouchDelta(0);
  };

  const handleLightboxSwipeStart = (e: React.TouchEvent) => {
    lightboxTouchStartX.current = e.touches[0].clientX;
  };

  const handleLightboxSwipeMove = () => {
    // Только начало жеста храним; направление определим в end
  };

  const handleLightboxSwipeEnd = (e: React.TouchEvent) => {
    const start = lightboxTouchStartX.current;
    if (start === null) return;
    const end = e.changedTouches[0].clientX;
    const diff = start - end;
    if (diff > SWIPE_THRESHOLD) goNext();
    else if (diff < -SWIPE_THRESHOLD) goPrev();
    lightboxTouchStartX.current = null;
  };

  const openLightbox = () => setLightboxOpen(true);
  const closeLightbox = () => setLightboxOpen(false);

  useEffect(() => {
    if (!lightboxOpen) return;
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeLightbox();
      if (hasMultiple && e.key === 'ArrowLeft') goPrev();
      if (hasMultiple && e.key === 'ArrowRight') goNext();
    };
    window.addEventListener('keydown', onKeyDown);
    document.body.style.overflow = 'hidden';
    return () => {
      window.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = '';
    };
  }, [lightboxOpen, hasMultiple, goPrev, goNext]);

  if (!images.length) {
    return (
      <div className="bg-gray-50 rounded-2xl overflow-hidden flex items-center justify-center w-full min-h-[300px]">
        <ImageIcon className="size-[1em] text-3xl text-gray-400" />
      </div>
    );
  }

  return (
    <>
      <div className="flex flex-col md:flex-row gap-4">
        {/* Тамбнейлы: горизонтально на мобильных, вертикально на md+ */}
        {hasMultiple && (
          <div className="flex flex-row md:flex-col gap-3 order-2 md:order-1 overflow-x-auto md:overflow-x-visible pb-2 md:pb-0 md:overflow-y-auto max-h-[calc(300px+1rem)] md:max-h-[550px] p-1">
            {images.map((img, idx) => (
              <button
                key={idx}
                type="button"
                onClick={() => onSelectIndex(idx)}
                className={`flex-shrink-0 w-16 h-16 md:w-20 md:h-20 rounded-lg overflow-hidden border-2 transition-all cursor-pointer ${selectedIndex === idx ? 'border-red-600 scale-105' : 'border-gray-200 hover:border-gray-300'}`}
                aria-label={`Фото ${idx + 1} из ${images.length}`}
              >
                {img ? (
                  <img
                    src={img}
                    alt=""
                    className="w-full h-full object-cover object-top"
                    loading={idx < 4 ? 'eager' : 'lazy'}
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center bg-gray-100">
                    <ImageIcon className="size-[1em] text-2xl text-gray-400" />
                  </div>
                )}
              </button>
            ))}
          </div>
        )}

        {/* Основное изображение: клик — лайтбокс; на мобильном свайп влево/вправо меняет фото */}
        <div className="flex-1 w-full md:max-w-[500px] order-1 md:order-2 min-w-0">
          <div
            className="bg-gray-50 rounded-2xl overflow-hidden mb-4 relative touch-pan-y"
            onTouchStart={handleMainTouchStart}
            onTouchMove={handleMainTouchMove}
            onTouchEnd={handleMainTouchEnd}
          >
            <button
              type="button"
              onClick={openLightbox}
              className="block w-full text-left focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-inset rounded-2xl"
              aria-label="Увеличить фото"
            >
              {currentImage ? (
                <img
                  src={currentImage}
                  alt={selectedIndex === 0 ? productName : `${productName} — фото ${selectedIndex + 1}`}
                  className="w-full h-[300px] sm:h-[400px] md:h-[500px] lg:h-[550px] object-cover object-top"
                  loading={selectedIndex === 0 ? 'eager' : 'lazy'}
                  draggable={false}
                />
              ) : (
                <div className="w-full h-[300px] sm:h-[400px] md:h-[500px] lg:h-[550px] flex items-center justify-center">
                  <ImageIcon className="size-[1em] text-3xl text-gray-400" />
                </div>
              )}
            </button>

            {/* Индикаторы слайдов (точки) */}
            {hasMultiple && (
              <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                {images.map((_, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => onSelectIndex(idx)}
                    className={`h-2 rounded-full transition-all cursor-pointer ${selectedIndex === idx ? 'bg-red-600 w-6' : 'bg-white/60 w-2 hover:bg-white/80'}`}
                    aria-label={`Фото ${idx + 1}`}
                  />
                ))}
              </div>
            )}

            {/* Подсказка про зум на десктопе */}
            <div className="absolute top-3 right-3 rounded-lg bg-black/40 text-white p-2 pointer-events-none hidden md:block">
              <ZoomIn className="size-[1em] text-xl" />
            </div>
          </div>
        </div>
      </div>

      {/* Лайтбокс: зум по клику, свайпы, кнопки */}
      {lightboxOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/90 cursor-zoom-out"
          role="dialog"
          aria-modal="true"
          aria-label="Галерея товара"
          onClick={closeLightbox}
        >
          <button
            type="button"
            onClick={(e) => { e.stopPropagation(); closeLightbox(); }}
            className="absolute top-4 right-4 z-10 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors cursor-pointer"
            aria-label="Закрыть"
          >
            <X className="size-[1em] text-2xl" />
          </button>

          {hasMultiple && (
            <>
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); goPrev(); }}
                className="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors cursor-pointer"
                aria-label="Предыдущее фото"
              >
                <ChevronLeft className="size-[1em] text-2xl" />
              </button>
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); goNext(); }}
                className="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors cursor-pointer"
                aria-label="Следующее фото"
              >
                <ChevronRight className="size-[1em] text-2xl" />
              </button>
            </>
          )}

          <div
            className="relative w-full h-full flex items-center justify-center p-4 pt-16 pb-16 overflow-hidden"
            onTouchStart={handleLightboxSwipeStart}
            onTouchEnd={handleLightboxSwipeEnd}
            onClick={(e) => e.stopPropagation()}
          >
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setLightboxZoom((z) => !z); }}
              className="block max-w-full max-h-full focus:outline-none focus:ring-2 focus:ring-white rounded-lg overflow-hidden cursor-zoom-in"
              aria-label={lightboxZoom ? 'Уменьшить' : 'Увеличить'}
            >
              {currentImage ? (
                <img
                  src={currentImage}
                  alt={productName}
                  className={`max-w-full max-h-full object-contain object-center transition-transform duration-200 select-none ${lightboxZoom ? 'scale-150 cursor-zoom-out' : 'scale-100'}`}
                  style={{ maxHeight: 'calc(100vh - 8rem)' }}
                  draggable={false}
                />
              ) : null}
            </button>
          </div>

          <div className="absolute bottom-4 left-1/2 -translate-x-1/2 text-white/80 text-sm">
            {selectedIndex + 1} / {images.length}
          </div>
        </div>
      )}
    </>
  );
}
