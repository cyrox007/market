import { useRef, useState } from 'react';
import { SmartLink } from '../../../../components/ui/primitives';
import { ChevronRight } from 'lucide-react';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Autoplay } from 'swiper/modules';
import type { Swiper as SwiperType } from 'swiper';
import { cn } from '../../../../lib/cn';
import type { PromoItem } from '../PromoBoard/lib/promo-types';
import SlideImage from '../SlideImage';

import 'swiper/css';

interface PromoBannerProps {
  slides?: PromoItem[];
  className?: string;
}

const AUTOPLAY_DELAY = 6000;

/** Баннер под «Хит продаж» во всю ширину. Замеры из Figma — market-docs/24-promo-banner.md */
export default function PromoBanner({ slides = [], className }: PromoBannerProps) {
  const swiperRef = useRef<SwiperType | null>(null);
  const progressRef = useRef<HTMLSpanElement>(null);
  const [active, setActive] = useState(0);

  if (!slides.length) return null;

  return (
    <section className={cn('mx-auto w-full max-w-[1440px]', className)}>
      <div className="relative isolate overflow-hidden">
        <Swiper
          modules={[Autoplay]}
          onSwiper={(instance) => (swiperRef.current = instance)}
          loop={slides.length > 1}
          autoplay={{ delay: AUTOPLAY_DELAY, disableOnInteraction: false, pauseOnMouseEnter: true }}
          onSlideChange={(instance) => setActive(instance.realIndex)}
          onAutoplayTimeLeft={(_instance, _timeLeft, progress) => {
            if (progressRef.current) progressRef.current.style.width = `${(1 - progress) * 100}%`;
          }}
        >
          {slides.map((slide) => (
            <SwiperSlide key={slide.id}>
              <BannerSlide slide={slide} />
            </SwiperSlide>
          ))}
        </Swiper>

        {slides.length > 1 ? (
          <div className="absolute inset-x-0 bottom-10 z-10 flex items-center justify-center gap-2 max-vsm:bottom-[26px] max-vsm:justify-end max-vsm:px-4">
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
                    'h-1.5 overflow-hidden rounded-lg outline-none transition-[width] duration-slow',
                    isActive
                      ? 'w-[39px] bg-ink-inverse/50'
                      : 'w-1.5 bg-ink-inverse/50 hover:opacity-80',
                  )}
                >
                  {isActive ? (
                    <span
                      ref={progressRef}
                      style={{ width: '0%' }}
                      className="block h-full rounded-lg bg-brand-yellow"
                    />
                  ) : null}
                </button>
              );
            })}
          </div>
        ) : null}
      </div>
    </section>
  );
}

function BannerSlide({ slide }: { slide: PromoItem }) {
  const { title, description, badge, image, imageMobile, to, linkText } = slide;

  return (
    <div className="relative isolate flex flex-col items-start justify-end gap-4 px-[100px] pb-10 pt-[54px] max-md:px-6 max-vsm:px-4 max-vsm:pb-6 max-vsm:pt-[38px]">
      <SlideImage image={image} imageMobile={imageMobile} lazy />
      <div className="absolute inset-0 -z-10 bg-black/[0.28]" />
      {/* Только на мобильном: текст ближе к низу, и макет темнит низ сильнее */}
      <div
        className="absolute inset-0 -z-10 hidden max-vsm:block"
        style={{
          backgroundImage:
            'linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.32) 38.942%, rgba(0,0,0,0.46) 100%)',
        }}
      />

      {/* leading-none на каждой строке: токены кеглей задают межстрочное сами и перебивают наследование */}
      <div className="flex flex-col gap-1 text-ink-inverse">
        {badge ? (
          <p className="text-16 font-medium leading-none max-vsm:text-14 max-vsm:font-normal">
            {badge}
          </p>
        ) : null}
        <div className="flex flex-col gap-2 max-vsm:gap-1">
          <h2 className="font-display text-36 font-bold leading-none tracking-[-0.72px] max-vsm:text-28 max-vsm:tracking-normal">
            {title}
          </h2>
          {description ? (
            <p className="whitespace-pre-line text-18 font-medium leading-none max-vsm:text-14 max-vsm:font-normal">
              {description}
            </p>
          ) : null}
        </div>
      </div>

      {to && linkText ? (
        <SmartLink
          to={to}
          className="inline-flex h-[52px] w-[302px] items-center justify-center gap-1 rounded-btn bg-brand-yellow px-7 text-16 font-medium text-ink shadow-card outline-none transition-colors hover:bg-brand-green hover:text-ink-inverse focus-visible:bg-brand-green focus-visible:text-ink-inverse motion-reduce:transition-none max-md:w-[224px] max-vsm:h-11 max-vsm:gap-0 max-vsm:text-14"
        >
          {linkText}
          <ChevronRight className="size-5" />
        </SmartLink>
      ) : null}
    </div>
  );
}
