import { useRef, useState } from 'react';
import type { Swiper as SwiperType } from 'swiper';

/** Swiper и его крайние положения: стрелкам и затуханию нужно знать, есть ли куда листать */
export function useCarouselEdges() {
  const swiperRef = useRef<SwiperType | null>(null);
  const [isBeginning, setBeginning] = useState(true);
  const [isEnd, setEnd] = useState(false);
  const [slideWidth, setSlideWidth] = useState(0);

  const sync = (instance: SwiperType) => {
    setBeginning(instance.isBeginning);
    setEnd(instance.isEnd);
    setSlideWidth(instance.slidesSizesGrid?.[0] ?? 0);
  };

  // Непрерывное onProgress: при медленном перетаскивании слайд ещё не сменился,
  // а края уже пройдены — без него стрелки и затухание запаздывают до отпускания
  const swiperProps = {
    onSwiper: (instance: SwiperType) => {
      swiperRef.current = instance;
      sync(instance);
    },
    onSlideChange: sync,
    onResize: sync,
    onProgress: sync,
  };

  return {
    isBeginning,
    isEnd,
    slideWidth,
    swiperProps,
    prev: () => swiperRef.current?.slidePrev(),
    next: () => swiperRef.current?.slideNext(),
  };
}
