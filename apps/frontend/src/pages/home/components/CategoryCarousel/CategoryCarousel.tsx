import { Swiper, SwiperSlide } from 'swiper/react';
import CategoryCard from './CategoryCard';
import type { CategoryCardItem } from './CategoryCard';
import { CarouselArrow, CarouselFade } from '../../../../components/ui/composites/Carousel';
import { useCarouselEdges } from '../../../../components/ui/composites/useCarouselEdges';
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
 * Отступы по краям прокрутки. В крайних положениях карточка встаёт вровень с колонкой
 * страницы — те же 100, что у остальных блоков. В середине прокрутки карточки уходят
 * под оба края.
 * Swiper применяет только один брейкпойнт, наибольший подходящий, и незаданное в нём
 * берёт из базовых значений, а не у соседа снизу. Поэтому оба смещения указаны в каждом.
 */
const EDGE = {
  vsm: { before: 16, after: 16 },
  sm: { before: 24, after: 24 },
  md: { before: 24, after: 24 },
  base: { before: 100, after: 100 },
};

export default function CategoryCarousel({ items = [], className }: CategoryCarouselProps) {
  const { isBeginning, isEnd, slideWidth, swiperProps, prev, next } = useCarouselEdges();

  if (!items.length) return null;

  // Полоса вдвое шире карточки: узкой не хватает, чтобы увести край в белый
  const fadeWidth = slideWidth * 2;

  return (
    <section className={cn(FULL_WIDTH, 'relative pt-[54px] max-md:pt-8', className)}>
      <div className="relative">
        <Swiper
          spaceBetween={16}
          slidesPerView="auto"
          slidesOffsetBefore={EDGE.vsm.before}
          slidesOffsetAfter={EDGE.vsm.after}
          breakpoints={{
            550: {
              slidesPerView: PER_VIEW.sm,
              slidesOffsetBefore: EDGE.sm.before,
              slidesOffsetAfter: EDGE.sm.after,
            },
            769: {
              slidesPerView: PER_VIEW.md,
              slidesOffsetBefore: EDGE.md.before,
              slidesOffsetAfter: EDGE.md.after,
            },
            980: {
              slidesPerView: PER_VIEW.base,
              slidesOffsetBefore: EDGE.base.before,
              slidesOffsetAfter: EDGE.base.after,
            },
          }}
          {...swiperProps}
        >
          {items.map((item) => (
            <SwiperSlide key={item.id} className="max-vsm:!w-[150px]">
              <CategoryCard item={item} />
            </SwiperSlide>
          ))}
        </Swiper>

        <CarouselFade
          side="left"
          hidden={isBeginning}
          width={fadeWidth}
          className="max-md:hidden"
        />
        <CarouselFade side="right" hidden={isEnd} width={fadeWidth} className="max-md:hidden" />

        <CarouselArrow
          side="prev"
          hidden={isBeginning}
          label="Предыдущие категории"
          onClick={prev}
          // Центр по картинке, а не по карточке: под ней ещё зазор 8 и строка подписи 17
          className="top-[calc(50%-12.5px)] left-[114px] max-md:left-4 max-vsm:left-2"
        />
        <CarouselArrow
          side="next"
          hidden={isEnd}
          label="Следующие категории"
          onClick={next}
          className="top-[calc(50%-12.5px)] right-[114px] max-md:right-4 max-vsm:right-2"
        />
      </div>
    </section>
  );
}
