import { useLocation } from 'react-router-dom';
import { Button } from '../../../../components/ui/primitives';
import { toCatalogSearch, type CatalogQuery } from '../../../../lib/catalog-query';
import PageLinks from './PageLinks';

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
          variant="muted"
          size="lg"
          shape="rounded"
          isLoading={isLoadingMore}
          onClick={onLoadMore}
          className="px-10 max-vsm:w-full"
        >
          Показать ещё
        </Button>
      )}
      <PageLinks pages={pages} lastPage={lastPage} hrefOf={hrefOf} onPage={onPage} />
    </div>
  );
}
