import type { MouseEvent } from 'react';
import { Link } from 'react-router-dom';
import { cn } from '../../../../lib/cn';
import { pageNumbers } from './pageNumbers';

interface PageLinksProps {
  /** Страницы на экране: после «Показать ещё» их несколько — все отмечены текущими */
  pages: { from: number; to: number };
  lastPage: number;
  /** Адрес страницы — настоящая ссылка для поисковиков и Ctrl+клика */
  hrefOf: (page: number) => string;
  onPage: (page: number) => void;
}

/** Обычный клик — без перехода по ссылке; с Ctrl / Cmd / колесом — новая вкладка */
const isPlainClick = (e: MouseEvent) =>
  e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;

/** Номера страниц — каталог и поиск (market-docs/50, 52) */
export default function PageLinks({ pages, lastPage, hrefOf, onPage }: PageLinksProps) {
  if (lastPage <= 1) return null;
  return (
    <nav aria-label="Страницы">
      <ul className="flex flex-wrap items-end justify-center gap-2">
        {pageNumbers(pages, lastPage).map((item, i) =>
          item === 'gap' ? (
            <li key={`gap-${i}`} aria-hidden className="text-14 font-semibold text-ink">
              ...
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
        // Figma 730:59762: 35 × 37 (py 10 + строка 14), радиус 10, Inter 14 Semi Bold
        'inline-flex h-[37px] min-w-[35px] items-center justify-center rounded-field px-1 text-14 font-semibold outline-none transition-colors',
        'hover:bg-brand-green hover:text-ink-inverse focus-visible:ring-2 focus-visible:ring-brand-green',
        current ? 'bg-brand-yellow text-ink' : 'bg-surface-grey text-ink',
      )}
    >
      {page}
    </Link>
  );
}
