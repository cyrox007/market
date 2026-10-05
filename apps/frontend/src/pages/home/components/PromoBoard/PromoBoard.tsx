import PromoSlider from './cards/PromoSlider';
import PromoCard from './cards/PromoCard';
import { cn } from '../../../../lib/cn';
import { PAGE_CONTAINER } from '../../../../lib/layout';
import type { PromoItem } from './lib/promo-types';

interface PromoBoardProps {
  slides?: PromoItem[];
  /**
   * Правые карточки — по именованным позициям, а не по порядку: бэкенд отдаёт их
   * отдельными полями, и при пустой верхней нижняя не должна встать на её место.
   */
  rightTop?: PromoItem | null;
  rightBottom?: PromoItem | null;
  className?: string;
}

/** Замеры и поведение — market-docs/17-promo-board.md */
export default function PromoBoard({
  slides = [],
  rightTop,
  rightBottom,
  className,
}: PromoBoardProps) {
  if (!slides.length) return null;

  return (
    <section className={cn(PAGE_CONTAINER, 'pt-4', className)}>
      <div className="grid grid-cols-[824fr_400fr] gap-4 max-md:grid-cols-1">
        <PromoSlider slides={slides} className="aspect-[824/461] max-vsm:aspect-[4/5]" />

        {rightTop || rightBottom ? (
          // Колонка вынута из потока: иначе её текст задаёт высоту строки сетки
          // и правая часть становится выше большой карточки
          <div className="relative max-md:hidden">
            <div className="absolute inset-0 flex flex-col gap-4">
              {rightTop ? <PromoCard item={rightTop} className="min-h-0 flex-1" /> : null}
              {rightBottom ? <PromoCard item={rightBottom} className="min-h-0 flex-1" /> : null}
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}
