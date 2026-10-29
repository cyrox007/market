/**
 * Canonical страницы пагинации: адрес раздела с бэкенда плюс `?page=N` при N > 1.
 *
 * Бэкенд про страницу не знает и отдаёт адрес раздела, поэтому у `/catalog/divany?page=3`
 * canonical указывал на первую страницу — для поисковика это «страница 3 копия первой,
 * не индексируй её», и товары, до которых ссылки есть только с дальних страниц, находятся
 * хуже. Сортировка и фильтры в canonical не попадают намеренно: там дубли, и чистый адрес
 * раздела — то, что нужно. Решение и доводы — market-docs/44.
 */
export function canonicalForPage<T extends string | null | undefined>(
  canonicalUrl: T,
  search: string,
): T | string {
  if (!canonicalUrl) return canonicalUrl;

  const raw = new URLSearchParams(search).get('page');
  const page = Number(raw);
  if (!raw || !Number.isInteger(page) || page < 2) return canonicalUrl;

  // У адреса с бэкенда параметров быть не должно, но свои мы в любом случае не копим
  const [base] = canonicalUrl.split('?');
  return `${base}?page=${page}`;
}
