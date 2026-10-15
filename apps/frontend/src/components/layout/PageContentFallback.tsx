import { PAGE_CONTAINER } from '../../lib/layout';
import ProductCardSkeleton from '../ui/ProductCardSkeleton';

/**
 * Видимый fallback при подгрузке lazy-чанка. Никогда не возвращает null —
 * иначе при гидратации SSR-контент сменяется пустым main и кажется «белый экран».
 * Вид — как заглушка каталога (market-docs/52).
 */
export default function PageContentFallback() {
  return (
    <div className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pt-5 max-vsm:pt-4`} aria-hidden>
      <div className="h-4 w-56 animate-pulse rounded-badge bg-surface-grey motion-reduce:animate-none" />
      <div className="mt-7 h-7 w-64 animate-pulse rounded-badge bg-surface-grey motion-reduce:animate-none" />
      <div className="mt-8 grid grid-cols-1 gap-x-3 gap-y-6 vsm:grid-cols-2 sm:grid-cols-3 md:grid-cols-4 md:gap-x-6 md:gap-y-8">
        {Array.from({ length: 8 }, (_, i) => (
          <ProductCardSkeleton key={i} />
        ))}
      </div>
    </div>
  );
}
