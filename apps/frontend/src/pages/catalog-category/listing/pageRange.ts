/** Какие страницы сейчас на экране: «Показать ещё» дописывает, всё остальное — заменяет */
export interface PageRange {
  from: number;
  to: number;
  /** Подпись фильтров и раздела: сменились — список начинается заново */
  filters: string;
}

export function resolvePageRange(
  prev: PageRange,
  page: number,
  filters: string,
  appending: number | null,
): PageRange {
  if (prev.filters === filters && prev.to === page) return prev;
  if (prev.filters === filters && appending === page && page === prev.to + 1) {
    return { ...prev, to: page };
  }
  return { from: page, to: page, filters };
}
