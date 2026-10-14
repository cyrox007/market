import type { MouseEvent } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Button } from '../../../../components/ui/primitives';
import { cn } from '../../../../lib/cn';
import { toCatalogSearch, type CatalogQuery } from '../../../../lib/catalog-query';
import { pageNumbers } from './pageNumbers';

interface CatalogPaginationProps {
  query: CatalogQuery;
  /** Страницы на экране: после «Показать ещё» их несколько */
  pages: { from: number; to: number };
  lastPage: number;
  hasMore: boolean;
  isLoadingMore: boolean;
  onLoadMore: () => void;
  onPage: (page: number) => void;
}

/** Обычный клик — без перехода по ссылке; с Ctrl / Cmd / колесом — новая вкладка */
const isPlainClick = (e: MouseEvent) =>
  e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;

/** «Показать ещё» и номера страниц под списком (market-docs/50) */
export default function CatalogPagination({
  query,
  pages,
  lastPage,
  hasMore,
  isLoadingMore,
  onLoadMore,
  onPage,
}: CatalogPaginationProps) {
  const { pathname } = useLocation();
  if (lastPage <= 1) return null;

  const hrefOf = (page: number) => {
    const search = toCatalogSearch({ ...query, page });
    return search ? `${pathname}?${search}` : pathname;
  };

  return (
    <div className="flex flex-col items-center gap-6 max-vsm:gap-5">
      {hasMore && (
        <Button
          variant="outline"
          size="lg"
          shape="rounded"
          isLoading={isLoadingMore}
          onClick={onLoadMore}
          className="bg-surface px-10 max-vsm:w-full"
        >
          Показать ещё
        </Button>
      )}
      <nav aria-label="Страницы">
        <ul className="flex flex-wrap items-center justify-center gap-2">
          {pageNumbers(pages, lastPage).map((item, i) =>
            item === 'gap' ? (
              <li
                key={`gap-${i}`}
                aria-hidden
                className="w-4 self-end text-center text-ink-secondary"
              >
                …
              </li>
            ) : (
              <li key={item}>
                <PageLink
                  page={item}
                  href={hrefOf(item)}
                  current={item >= pages.from && item <= pages.to}
                  onPage={onPage}
                />
              </li>
            ),
          )}
        </ul>
      </nav>
    </div>
  );
}

function PageLink({
  page,
  href,
  current,
  onPage,
}: {
  page: number;
  href: string;
  current: boolean;
  onPage: (page: number) => void;
}) {
  return (
    <Link
      to={href}
      aria-label={`Страница ${page}`}
      aria-current={current ? 'page' : undefined}
      onClick={(e) => {
        if (!isPlainClick(e)) return;
        e.preventDefault();
        onPage(page);
      }}
      className={cn(
        'inline-flex size-9 items-center justify-center rounded-[8px] text-14 font-medium outline-none transition-colors',
        'hover:bg-brand-green hover:text-ink-inverse focus-visible:ring-2 focus-visible:ring-brand-green',
        current ? 'bg-brand-yellow text-ink' : 'bg-surface-grey text-ink',
      )}
    >
      {page}
    </Link>
  );
}
