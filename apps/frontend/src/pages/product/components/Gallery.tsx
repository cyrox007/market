import { useEffect, useRef, useState } from 'react';
import { ImageIcon } from 'lucide-react';
import { cn } from '../../../lib/cn';
import { prefersReducedMotion } from '../../../lib/motion';
import { MoreThumbsButton, ThumbImage } from './Thumbs';

interface Props {
  images: string[];
  productName: string;
  selectedIndex: number;
  onSelectIndex: (index: number) => void;
  /** Просмотр на весь экран с этого фото (задача 07) */
  onOpen: (index: number) => void;
}

/** Сколько превью видно в столбике (Figma 669:43389) */
const WINDOW = 4;
/** Промежуток между слайдами ленты, px — как gap-3 */
const STRIP_GAP = 12;

const photoAlt = (name: string, i: number) => (i === 0 ? name : `${name} — фото ${i + 1}`);

/** Фото товара: шире 980 — столбик превью и главное фото, уже — лента со свайпом (market-docs/57) */
export default function Gallery({
  images,
  productName,
  selectedIndex,
  onSelectIndex,
  onOpen,
}: Props) {
  if (images.length === 0) {
    return (
      <div className="flex aspect-square w-full flex-col items-center justify-center gap-2 rounded-btn bg-surface-grey text-14 text-ink-secondary">
        <ImageIcon aria-hidden="true" className="size-8" />
        Фото скоро появится
      </div>
    );
  }
  return (
    <>
      <DesktopGallery
        images={images}
        productName={productName}
        selectedIndex={selectedIndex}
        onSelectIndex={onSelectIndex}
        onOpen={onOpen}
      />
      <PhotoStrip
        images={images}
        productName={productName}
        selectedIndex={selectedIndex}
        onSelectIndex={onSelectIndex}
        onOpen={onOpen}
      />
    </>
  );
}

function DesktopGallery({ images, productName, selectedIndex, onSelectIndex, onOpen }: Props) {
  const hasThumbs = images.length > 1;
  const canScroll = images.length > WINDOW;
  const lastOffset = Math.max(0, images.length - WINDOW);

  const [offset, setOffset] = useState(0);
  // Выбрали фото вне окна (из просмотра или ленты) — окно сдвигается к нему; стрелку не отменяем
  const [prevSelected, setPrevSelected] = useState(selectedIndex);
  if (prevSelected !== selectedIndex) {
    setPrevSelected(selectedIndex);
    if (selectedIndex < offset) setOffset(selectedIndex);
    else if (selectedIndex >= offset + WINDOW)
      setOffset(Math.min(selectedIndex - WINDOW + 1, lastOffset));
  }
  const shown = Math.min(offset, lastOffset);

  const current = images[selectedIndex] ?? images[0];

  return (
    <div
      className={cn(
        'hidden gap-3 md:grid',
        hasThumbs ? 'grid-cols-[minmax(0,146.5fr)_minmax(0,616fr)]' : 'grid-cols-1',
      )}
    >
      {hasThumbs && (
        // Высота столбика — высота главного фото; превью квадратные, шаг — ширина столбика
        <div className="relative overflow-hidden [container-type:inline-size]">
          <ul
            aria-label="Фото товара"
            className="absolute inset-x-0 top-0 flex flex-col gap-3 transition-transform duration-slow ease-out motion-reduce:transition-none"
            style={{ transform: `translateY(calc(${-shown} * (100cqw + ${STRIP_GAP}px)))` }}
          >
            {images.map((src, i) => {
              const inWindow = i >= shown && i < shown + WINDOW;
              const active = i === selectedIndex;
              return (
                <li key={`${src}-${i}`} aria-hidden={inWindow ? undefined : true}>
                  <button
                    type="button"
                    tabIndex={inWindow ? undefined : -1}
                    aria-label={`Фото ${i + 1} из ${images.length}`}
                    aria-current={active || undefined}
                    onClick={() => onSelectIndex(i)}
                    className={cn(
                      'relative block aspect-square w-full overflow-hidden rounded-btn outline-none',
                      'transition-[border-color,opacity] duration-200 motion-reduce:transition-none',
                      'focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
                      active
                        ? 'border-2 border-brand-yellow'
                        : 'border border-surface-stroke/[0.08] hover:opacity-80',
                    )}
                  >
                    <ThumbImage src={src} active={active} loading={i < WINDOW ? 'eager' : 'lazy'} />
                  </button>
                </li>
              );
            })}
          </ul>
          {canScroll && (
            <MoreThumbsButton
              label="Следующие фото"
              direction="down"
              onClick={() => setOffset(shown >= lastOffset ? 0 : shown + 1)}
              className="inset-x-0 bottom-0 h-9"
            />
          )}
        </div>
      )}

      <button
        type="button"
        aria-label={`Открыть фото ${selectedIndex + 1} на весь экран`}
        onClick={() => onOpen(selectedIndex)}
        className="relative aspect-square w-full cursor-zoom-in overflow-hidden rounded-btn bg-surface-grey outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
      >
        <img
          key={current}
          src={current}
          alt={photoAlt(productName, selectedIndex)}
          fetchPriority="high"
          className="absolute inset-0 size-full animate-photoIn object-cover motion-reduce:animate-none"
        />
      </button>
    </div>
  );
}

function PhotoStrip({ images, productName, selectedIndex, onSelectIndex, onOpen }: Props) {
  const stripRef = useRef<HTMLDivElement>(null);
  // Последнее, что выбрала сама прокрутка: его не прокручиваем обратно
  const fromScroll = useRef(selectedIndex);

  const step = () => {
    const slide = stripRef.current?.children[0] as HTMLElement | undefined;
    return slide ? slide.offsetWidth + STRIP_GAP : 0;
  };

  const handleScroll = () => {
    const strip = stripRef.current;
    const width = step();
    if (!strip || !width) return;
    const index = Math.min(images.length - 1, Math.max(0, Math.round(strip.scrollLeft / width)));
    if (index === fromScroll.current) return;
    fromScroll.current = index;
    onSelectIndex(index);
  };

  // Фото выбрали снаружи (смена варианта, просмотр) — подкручиваем ленту
  useEffect(() => {
    const strip = stripRef.current;
    if (!strip || selectedIndex === fromScroll.current) return;
    fromScroll.current = selectedIndex;
    strip.scrollTo?.({
      left: selectedIndex * step(),
      behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
  }, [selectedIndex]);

  return (
    <div className="flex flex-col gap-2.5 md:hidden">
      <div
        ref={stripRef}
        data-testid="gallery-strip"
        onScroll={handleScroll}
        className="flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {images.map((src, i) => (
          <button
            key={`${src}-${i}`}
            type="button"
            aria-label={`Открыть фото ${i + 1} на весь экран`}
            onClick={() => onOpen(i)}
            // Одно фото — во всю ширину; несколько — виден край следующего (Figma 595:39583)
            className={cn(
              'relative aspect-square shrink-0 snap-start overflow-hidden rounded-[8px] bg-surface-grey',
              images.length > 1 ? 'w-[min(392px,82%)]' : 'w-full',
            )}
          >
            <img
              src={src}
              alt={photoAlt(productName, i)}
              loading={i < 2 ? 'eager' : 'lazy'}
              decoding="async"
              className="absolute inset-0 size-full object-cover"
            />
          </button>
        ))}
      </div>
      {images.length > 1 && (
        <div
          data-testid="gallery-dots"
          aria-hidden="true"
          className="flex items-center justify-center gap-2"
        >
          {images.map((src, i) => (
            <span
              key={`${src}-${i}`}
              data-active={i === selectedIndex}
              className={cn(
                'h-1.5 rounded-[3px] transition-[width,background-color] duration-200 motion-reduce:transition-none',
                i === selectedIndex ? 'w-[22px] bg-brand-yellow' : 'w-1.5 bg-surface-border',
              )}
            />
          ))}
        </div>
      )}
    </div>
  );
}
