import { useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { SliderArrowIcon } from '../../../../../components/ui/icons/slider-arrow';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Autoplay } from 'swiper/modules';
import type { Swiper as SwiperType } from 'swiper';
import { Badge } from '../../../../../components/ui/primitives';
import { cn } from '../../../../../lib/cn';
import type { PromoItem } from '../lib/promo-types';

import 'swiper/css';

interface PromoSliderProps {
  slides: PromoItem[];
  className?: string;
}

const AUTOPLAY_DELAY = 6000;

const ARROW =
  'absolute top-1/2 z-10 h-[37px] w-[17px] -translate-y-1/2 text-ink-inverse outline-none transition-opacity hover:opacity-70 focus-visible:opacity-70 max-vsm:hidden';

/** Большая карточка блока: карусель промо. Замеры из Figma — market-docs/17-promo-board.md */
export default function PromoSlider({ slides, className }: PromoSliderProps) {
  const swiperRef = useRef<SwiperType | null>(null);
  const progressRef = useRef<HTMLSpanElement>(null);
  const [active, setActive] = useState(0);

  return (
    <div className={cn('relative isolate overflow-hidden rounded-card bg-surface-grey', className)}>
      <Swiper
        modules={[Autoplay]}
        onSwiper={(instance) => (swiperRef.current = instance)}
        loop={slides.length > 1}
        autoplay={{ delay: AUTOPLAY_DELAY, disableOnInteraction: false, pauseOnMouseEnter: true }}
        onSlideChange={(instance) => setActive(instance.realIndex)}
        // Событие Swiper, а не свой таймер: оно замирает вместе с автопрокруткой на паузе
        onAutoplayTimeLeft={(_instance, _timeLeft, progress) => {
          if (progressRef.current) progressRef.current.style.width = `${(1 - progress) * 100}%`;
        }}
        className="size-full"
      >
        {slides.map((slide) => (
          <SwiperSlide key={slide.id}>
            <PromoSlide slide={slide} />
          </SwiperSlide>
        ))}
      </Swiper>

      {slides.length > 1 ? (
        <>
          <button
            type="button"
            aria-label="Предыдущий слайд"
            onClick={() => swiperRef.current?.slidePrev()}
            className={cn(ARROW, 'left-[42px]')}
          >
            <SliderArrowIcon className="size-full" />
          </button>
          <button
            type="button"
            aria-label="Следующий слайд"
            onClick={() => swiperRef.current?.slideNext()}
            className={cn(ARROW, 'right-[42px]')}
          >
            <SliderArrowIcon className="size-full -scale-x-100" />
          </button>

          <div className="absolute inset-x-0 bottom-[42px] z-10 flex items-center justify-center gap-2 max-md:bottom-6">
            {slides.map((slide, index) => {
              const isActive = index === active;
              return (
                <button
                  key={slide.id}
                  type="button"
                  aria-label={`Слайд ${index + 1}`}
                  aria-current={isActive}
                  onClick={() => swiperRef.current?.slideToLoop(index)}
                  className={cn(
                    'h-1.5 overflow-hidden rounded-[3px] outline-none transition-[width] duration-slow',
                    isActive
                      ? 'w-[22px] bg-ink-inverse/50'
                      : 'w-1.5 bg-ink-inverse/50 hover:opacity-80',
                  )}
                >
                  {isActive ? (
                    <span
                      ref={progressRef}
                      style={{ width: '0%' }}
                      className="block h-full rounded-[3px] bg-brand-yellow"
                    />
                  ) : null}
                </button>
              );
            })}
          </div>
        </>
      ) : null}
    </div>
  );
}

function PromoSlide({ slide }: { slide: PromoItem }) {
  const { title, description, badge, image, to } = slide;

  const content = (
    <>
      {image ? (
        <img src={image} alt="" className="absolute inset-0 -z-10 size-full object-cover" />
      ) : null}

      {/* Тень из макета: чёрный 60% слева, к правому краю сходит на нет */}
      <div className="absolute inset-0 -z-10 bg-gradient-to-r from-black/60 to-transparent" />

      {/* Место под бейдж занято всегда, иначе на слайде без него заголовок уезжает вверх */}
      <Badge
        variant="sale"
        size="md"
        className={cn('uppercase tracking-[0.48px]', !badge && 'invisible')}
      >
        {badge || ' '}
      </Badge>

      <div className="flex max-w-[458px] flex-col gap-3 text-ink-inverse">
        <h2 className="font-display text-52 font-bold leading-none max-xl:text-44 max-vsm:text-32">
          {title}
        </h2>
        {/* Две строки резервируются в em: высота едет за кеглем и не требует чисел по брейкпойнтам */}
        <p className="min-h-[2.5em] whitespace-pre-line text-18 font-medium leading-tight max-vsm:text-13">
          {description}
        </p>
      </div>
    </>
  );

  // Слева 80: стрелка занимает 42…59, между ней и текстом остаётся воздух
  const shell =
    'relative isolate flex size-full flex-col items-start justify-center gap-12 px-5 py-[42px] pl-20 max-md:gap-6 max-md:py-6 max-vsm:px-4 max-vsm:pl-4';

  return to ? (
    <Link to={to} className={cn(shell, 'outline-none')}>
      {content}
    </Link>
  ) : (
    <div className={shell}>{content}</div>
  );
}
