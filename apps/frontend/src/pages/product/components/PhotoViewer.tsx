import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import PresencePortal from '../../../components/ui/primitives/PresencePortal';
import { useDialog } from '../../../components/ui/primitives/useDialog';
import { transitionMs, useCloseUnlessLeaving } from '../../../components/ui/primitives/usePresence';
import { cn } from '../../../lib/cn';

interface Props {
  open: boolean;
  images: string[];
  productName: string;
  /** Общее с галереей страницы: открывается на нём, после закрытия на странице — последнее */
  index: number;
  onIndexChange: (index: number) => void;
  onClose: () => void;
}

const MOTION = { enterMs: 200, leaveMs: 150 };
/** Сдвиг пальца, после которого это свайп, px */
const SWIPE = 50;
/**
 * Ширина области: по высоте — экран минус отступы, крестик и ряд превью (Figma 595:39543, 595:39556).
 * По ширине — % окна, не vw: при scrollbar-gutter на <html> vw короче экрана
 */
const AREA_WIDTH =
  'w-[min(calc(100dvh-48px-48px-16px-76px),100%)] vsm:w-[min(calc(100dvh-48px-48px-16px-100px),100%)] md:w-[min(calc(100dvh-64px-16px-96px),calc(100%-192px))]';

/** Просмотр фото на весь экран: белый фон, стрелки, ряд превью, крестик (market-docs/56) */
export default function PhotoViewer(props: Props) {
  return (
    <PresencePortal open={props.open} motion={MOTION}>
      {({ shown, leaving }) => <ViewerPanel {...props} shown={shown} leaving={leaving} />}
    </PresencePortal>
  );
}

function ViewerPanel({
  images,
  productName,
  index,
  onIndexChange,
  onClose,
  shown,
  leaving,
}: Props & { shown: boolean; leaving: boolean }) {
  const panelRef = useRef<HTMLDivElement>(null);
  const onCloseRef = useCloseUnlessLeaving(onClose, leaving);
  const trapTab = useDialog(panelRef, onCloseRef);
  const many = images.length > 1;
  const current = Math.min(index, images.length - 1);

  const go = (delta: number) => onIndexChange((current + delta + images.length) % images.length);
  const goRef = useRef(go);
  useLayoutEffect(() => {
    goRef.current = go;
  });

  useEffect(() => {
    if (!many) return;
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === 'ArrowLeft') goRef.current(-1);
      if (event.key === 'ArrowRight') goRef.current(1);
    };
    document.addEventListener('keydown', handleKey);
    return () => document.removeEventListener('keydown', handleKey);
  }, [many]);

  const swipeStart = useRef<number | null>(null);
  const onPointerDown = (event: PointerEvent) => {
    swipeStart.current = event.clientX;
  };
  const onPointerUp = (event: PointerEvent) => {
    if (swipeStart.current === null || !many) return;
    const dx = event.clientX - swipeStart.current;
    swipeStart.current = null;
    if (dx <= -SWIPE) go(1);
    else if (dx >= SWIPE) go(-1);
  };

  return (
    <div
      ref={panelRef}
      role="dialog"
      aria-modal="true"
      aria-label="Фото товара"
      tabIndex={-1}
      onKeyDown={trapTab}
      className={cn(
        'fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-surface px-4 py-6 outline-none vsm:px-6 md:px-4 md:py-8',
        'transition-opacity motion-reduce:transition-none',
        shown ? 'opacity-100' : 'opacity-0',
        leaving && 'pointer-events-none',
      )}
      style={transitionMs(MOTION, leaving)}
    >
      <button
        type="button"
        aria-label="Закрыть"
        onClick={() => onCloseRef.current()}
        className="absolute right-4 top-4 rounded-pill p-2 text-ink transition-colors hover:text-brand-green md:right-6 md:top-6"
      >
        <X aria-hidden="true" className="size-6 md:size-8" />
      </button>

      {/* Квадрат; шире 980 по бокам место под стрелки, уже — стрелок нет, листают свайпом */}
      <div
        data-testid="viewer-stage"
        onPointerDown={onPointerDown}
        onPointerUp={onPointerUp}
        onPointerCancel={() => (swipeStart.current = null)}
        className={cn('relative flex aspect-square touch-pan-y select-none items-center justify-center', AREA_WIDTH)}
      >
        <StagePhoto
          key={images[current]}
          src={images[current]}
          alt={current === 0 ? productName : `${productName} — фото ${current + 1}`}
        />
        {many && (
          <>
            <ArrowButton side="left" onClick={() => go(-1)} />
            <ArrowButton side="right" onClick={() => go(1)} />
          </>
        )}
      </div>

      {many && <ThumbRow images={images} current={current} onSelect={onIndexChange} />}
    </div>
  );
}

/** Фото по размеру картинки, а не всего квадрата: скругление 12 — по углам самого фото */
function StagePhoto({ src, alt }: { src: string; alt: string }) {
  // Ширина к высоте; до загрузки — как квадрат
  const [ratio, setRatio] = useState(1);
  return (
    <img
      src={src}
      alt={alt}
      draggable={false}
      onLoad={(event) => {
        const { naturalWidth, naturalHeight } = event.currentTarget;
        if (naturalWidth && naturalHeight) setRatio(naturalWidth / naturalHeight);
      }}
      style={{ width: `${Math.min(1, ratio) * 100}%`, aspectRatio: ratio }}
      className="max-h-full animate-photoIn rounded-btn object-cover motion-reduce:animate-none"
    />
  );
}

function ArrowButton({ side, onClick }: { side: 'left' | 'right'; onClick: () => void }) {
  const Icon = side === 'left' ? ChevronLeft : ChevronRight;
  return (
    <button
      type="button"
      aria-label={side === 'left' ? 'Предыдущее фото' : 'Следующее фото'}
      onClick={onClick}
      className={cn(
        'absolute top-1/2 hidden size-16 -translate-y-1/2 items-center justify-center rounded-pill bg-surface text-ink shadow-card md:flex',
        'transition-colors hover:bg-brand-yellow',
        side === 'left' ? '-left-[100px]' : '-right-[100px]',
      )}
    >
      <Icon aria-hidden="true" className="size-6" />
    </button>
  );
}

function ThumbRow({
  images,
  current,
  onSelect,
}: {
  images: string[];
  current: number;
  onSelect: (index: number) => void;
}) {
  const rowRef = useRef<HTMLDivElement>(null);
  const [canScroll, setCanScroll] = useState(false);

  const measure = () => {
    const row = rowRef.current;
    if (row) setCanScroll(row.scrollLeft + row.clientWidth < row.scrollWidth - 1);
  };

  useEffect(() => {
    measure();
    window.addEventListener('resize', measure);
    return () => window.removeEventListener('resize', measure);
  }, []);

  // Выбранное превью всегда в ряду видно
  useEffect(() => {
    const thumb = rowRef.current?.children[current] as HTMLElement | undefined;
    thumb?.scrollIntoView?.({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
  }, [current]);

  return (
    <div className={cn('relative', AREA_WIDTH)}>
      <div
        ref={rowRef}
        onScroll={measure}
        // Мало превью — по центру; auto-поля крайних, а не justify-center: тот обрезал бы начало ряда
        className="flex gap-3 overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:gap-4 [&>:first-child]:ml-auto [&>:last-child]:mr-auto"
      >
        {images.map((src, i) => (
          <button
            key={`${src}-${i}`}
            type="button"
            aria-label={`Фото ${i + 1} из ${images.length}`}
            aria-current={i === current || undefined}
            onClick={() => onSelect(i)}
            className={cn(
              'relative size-[76px] shrink-0 overflow-hidden rounded-btn transition-[border-color,opacity] duration-200 vsm:size-[100px] md:size-24',
              i === current
                ? 'border-2 border-brand-yellow'
                : 'border border-surface-stroke/[0.08] hover:opacity-80',
            )}
          >
            <img
              src={src}
              alt=""
              loading="lazy"
              className="absolute inset-0 size-full object-cover"
            />
            {i === current && (
              <span aria-hidden="true" className="absolute inset-0 bg-black/[0.32]" />
            )}
          </button>
        ))}
      </div>
      {canScroll && (
        <button
          type="button"
          aria-label="Ещё превью"
          onClick={() => {
            // Шаг — от превью до соседнего: размер и промежуток зависят от ширины экрана
            const [a, b] = Array.from(rowRef.current?.children ?? []) as HTMLElement[];
            rowRef.current?.scrollBy({ left: b.offsetLeft - a.offsetLeft, behavior: 'smooth' });
          }}
          className="absolute inset-y-0 right-0 flex w-9 items-center justify-center rounded-swatch bg-surface-grey text-ink backdrop-blur-[2px] transition-colors hover:bg-surface-border"
        >
          <ChevronRight aria-hidden="true" className="size-6" />
        </button>
      )}
    </div>
  );
}
