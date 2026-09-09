import { useRef, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Swiper, SwiperSlide } from 'swiper/react';
import type { Swiper as SwiperType } from 'swiper';
import CategoryCard from './CategoryCard';
import type { CategoryCardItem } from './CategoryCard';
import { IconButton } from '../../../../components/ui/primitives';
import { cn } from '../../../../lib/cn';

import 'swiper/css';

interface CategoryCarouselProps {
  items?: CategoryCardItem[];
  className?: string;
}

/** Дробное число карточек — намеренно: край следующей подсказывает, что список едет */
const PER_VIEW = { base: 7.14, md: 4.2, sm: 3.2 };

/** Карусель идёт от края до края: боковых отступов страницы у неё нет */
const FULL_WIDTH = 'mx-auto w-full max-w-[1440px]';

/**
 * Отступ от края в начале и в конце прокрутки. На десктопе его нет — карточки
 * упираются в край. Swiper применяет только один брейкпойнт, наибольший подходящий,
 * и незаданное в нём берёт из базовых значений, а не у соседа снизу. Поэтому
 * смещение указано в каждом.
 */
const EDGE = { vsm: 16, sm: 24, md: 24, base: 0 };

export default function CategoryCarousel({ items = [], className }: CategoryCarouselProps) {
  const swiperRef = useRef<SwiperType | null>(null);
  const [isBeginning, setBeginning] = useState(true);
  const [isEnd, setEnd] = useState(false);
  const [slideWidth, setSlideWidth] = useState(0);

  if (!items.length) return null;

  const sync = (instance: SwiperType) => {
    setBeginning(instance.isBeginning);
    setEnd(instance.isEnd);
    // Полоса вдвое шире карточки: узкой не хватает, чтобы увести край в белый
    setSlideWidth((instance.slidesSizesGrid?.[0] ?? 0) * 2);
  };

  return (
    <section className={cn(FULL_WIDTH, 'relative pt-[54px] max-md:pt-8', className)}>
      <div className="relative">
        <Swiper
          spaceBetween={16}
          slidesPerView="auto"
          slidesOffsetBefore={EDGE.vsm}
          slidesOffsetAfter={EDGE.vsm}
          breakpoints={{
            550: {
              slidesPerView: PER_VIEW.sm,
              slidesOffsetBefore: EDGE.sm,
              slidesOffsetAfter: EDGE.sm,
            },
            769: {
              slidesPerView: PER_VIEW.md,
              slidesOffsetBefore: EDGE.md,
              slidesOffsetAfter: EDGE.md,
            },
            980: {
              slidesPerView: PER_VIEW.base,
              slidesOffsetBefore: EDGE.base,
              slidesOffsetAfter: EDGE.base,
            },
          }}
          onSwiper={(instance) => {
            swiperRef.current = instance;
            sync(instance);
          }}
          onSlideChange={sync}
          onResize={sync}
        >
          {items.map((item) => (
            <SwiperSlide key={item.id} className="max-vsm:!w-[150px]">
              <CategoryCard item={item} />
            </SwiperSlide>
          ))}
        </Swiper>

        <Fade side="left" hidden={isBeginning} width={slideWidth} />
        <Fade side="right" hidden={isEnd} width={slideWidth} />

        <CarouselArrow
          side="prev"
          hidden={isBeginning}
          onClick={() => swiperRef.current?.slidePrev()}
        />
        <CarouselArrow side="next" hidden={isEnd} onClick={() => swiperRef.current?.slideNext()} />
      </div>
    </section>
  );
}

function Fade({ side, hidden, width }: { side: 'left' | 'right'; hidden: boolean; width: number }) {
  return (
    <div
      aria-hidden="true"
      style={width ? { width } : undefined}
      className={cn(
        // z-10 обязателен: Swiper объявляет себе z-index 1 и иначе перекрывает затухание
        // За пределы: сверху 1, слева 1, справа 2 — иначе по краю остаётся волосок карточки
        'pointer-events-none absolute -top-px bottom-0 z-10 w-24 transition-opacity duration-slow max-md:hidden',
        side === 'left'
          ? '-left-px bg-gradient-to-r from-surface via-surface/80 to-transparent'
          : '-right-0.5 bg-gradient-to-l from-surface via-surface/80 to-transparent',
        hidden && 'opacity-0',
      )}
    />
  );
}

function CarouselArrow({
  side,
  hidden,
  onClick,
}: {
  side: 'prev' | 'next';
  hidden: boolean;
  onClick: () => void;
}) {
  const isPrev = side === 'prev';
  return (
    <IconButton
      variant="white"
      size="md"
      elevated
      label={isPrev ? 'Предыдущие категории' : 'Следующие категории'}
      onClick={onClick}
      className={cn(
        // Центр по картинке, а не по карточке: под ней ещё зазор 8 и строка подписи 17
        'absolute top-[calc(50%-12.5px)] z-10 -translate-y-1/2',
        isPrev
          ? 'left-[114px] max-md:left-4 max-vsm:left-2'
          : 'right-[114px] max-md:right-4 max-vsm:right-2',
        hidden && 'pointer-events-none opacity-0',
      )}
    >
      {isPrev ? <ChevronLeft className="size-5" /> : <ChevronRight className="size-5" />}
    </IconButton>
  );
}
