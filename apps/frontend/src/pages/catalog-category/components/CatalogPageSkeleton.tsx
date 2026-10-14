import { PAGE_CONTAINER } from '../../../lib/layout';
import CategoryBreadcrumbs from './CategoryBreadcrumbs';

const BAR = 'animate-pulse rounded bg-surface-grey motion-reduce:animate-none';

/** Раздел ещё не загружен: лендинг это или список — неизвестно, поэтому нейтральная заглушка */
export default function CatalogPageSkeleton({ name }: { name?: string }) {
  return (
    <main className="bg-surface" aria-busy>
      <div
        className={`${PAGE_CONTAINER} pb-16 pt-6 max-md:pb-12 max-md:pt-5 max-vsm:pb-10 max-vsm:pt-4`}
      >
        {name ? (
          <CategoryBreadcrumbs name={name} />
        ) : (
          <div className={`${BAR} h-4 w-56 max-vsm:h-3.5`} />
        )}
        {name ? (
          <h1 className="mt-7 font-display text-32 font-bold leading-none text-ink max-md:sr-only">
            {name}
          </h1>
        ) : (
          <div className={`${BAR} mt-7 h-8 w-64 max-md:hidden`} />
        )}
        <div className="mt-8 grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 md:grid-cols-4 md:gap-x-6 md:gap-y-8 max-md:mt-6">
          {Array.from({ length: 8 }, (_, i) => (
            <div key={i} className={`${BAR} aspect-square rounded-btn`} />
          ))}
        </div>
      </div>
    </main>
  );
}
