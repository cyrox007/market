import { useId } from 'react';
import { Swiper, SwiperSlide } from 'swiper/react';
import ProductCardConnected from '../../../components/ui/ProductCardConnected';
import { CarouselArrow, CarouselFade } from '../../../components/ui/composites/Carousel';
import { useCarouselEdges } from '../../../components/ui/composites/useCarouselEdges';
import type { Product } from '../../../lib/api';

import 'swiper/css';

/**
 * Карусель «Похожие товары» (Figma 919:75808): карточки 298 через 16, справа уходит за колонку до
 * края экрана (не дальше 96 — как на 1440) и там гаснет, край следующей подсказывает прокрутку.
 */
export default function RelatedProducts({ products }: { products: Product[] }) {
  const titleId = useId();
  const { isBeginning, isEnd, swiperProps, prev, next } = useCarouselEdges();

  if (products.length === 0) return null;

  return (
    <section aria-labelledby={titleId} className="mb-12">
      <h2
        id={titleId}
        className="mb-3 font-display text-24 font-bold tracking-[-0.02em] text-ink max-vsm:text-16"
      >
        Похожие товары
      </h2>
      <div className="relative">
        <div className="relative mr-[max(calc(50%-50vw),-96px)]" data-product-shop>
          <Swiper spaceBetween={16} slidesPerView="auto" {...swiperProps}>
            {products.map((item, index) => (
              <SwiperSlide key={item.id} className="!w-[298px]">
                <ProductCardConnected product={item} priority={index < 4} />
              </SwiperSlide>
            ))}
          </Swiper>
          <CarouselFade side="right" hidden={isEnd} width={100} veil className="max-md:hidden" />
        </div>
        {/* По центру фото: оно квадратное, 298 */}
        <CarouselArrow
          side="prev"
          hidden={isBeginning}
          glass
          label="Предыдущие товары"
          onClick={prev}
          className="left-0 top-[149px] max-vsm:hidden"
        />
        <CarouselArrow
          side="next"
          hidden={isEnd}
          glass
          label="Следующие товары"
          onClick={next}
          className="right-0 top-[149px] max-vsm:hidden"
        />
      </div>
    </section>
  );
}
