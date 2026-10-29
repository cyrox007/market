import { act, renderHook } from '@testing-library/react';
import type { Swiper as SwiperType } from 'swiper';
import { describe, expect, it, vi } from 'vitest';
import { useCarouselEdges } from './useCarouselEdges';

const swiper = (over: Partial<SwiperType> = {}) =>
  ({
    isBeginning: true,
    isEnd: false,
    slidesSizesGrid: [240],
    slidePrev: vi.fn(),
    slideNext: vi.fn(),
    ...over,
  }) as unknown as SwiperType;

describe('края карусели', () => {
  it('до появления Swiper — в начале, листать вперёд можно', () => {
    const { result } = renderHook(() => useCarouselEdges());
    expect(result.current).toMatchObject({ isBeginning: true, isEnd: false, slideWidth: 0 });
  });

  it('при создании берёт края и ширину слайда', () => {
    const { result } = renderHook(() => useCarouselEdges());
    act(() => result.current.swiperProps.onSwiper(swiper({ isBeginning: false, isEnd: true })));
    expect(result.current).toMatchObject({ isBeginning: false, isEnd: true, slideWidth: 240 });
  });

  it('при перетаскивании края обновляются сразу, не дожидаясь смены слайда', () => {
    const { result } = renderHook(() => useCarouselEdges());
    act(() => result.current.swiperProps.onSwiper(swiper()));
    act(() => result.current.swiperProps.onProgress(swiper({ isBeginning: false, isEnd: true })));
    expect(result.current.isBeginning).toBe(false);
    expect(result.current.isEnd).toBe(true);
  });

  it('стрелки листают тот Swiper, что создан', () => {
    const { result } = renderHook(() => useCarouselEdges());
    const instance = swiper();
    act(() => result.current.swiperProps.onSwiper(instance));
    result.current.next();
    result.current.prev();
    expect(instance.slideNext).toHaveBeenCalledOnce();
    expect(instance.slidePrev).toHaveBeenCalledOnce();
  });
});
