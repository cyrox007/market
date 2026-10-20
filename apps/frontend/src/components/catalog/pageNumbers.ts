export type PageItem = number | 'gap';

/** Первая, последняя, показанные страницы с соседями; у краёв — по три номера (макет: 1 2 3 … 16) */
export function pageNumbers(shown: { from: number; to: number }, last: number): PageItem[] {
  const pages = new Set([1, last]);
  for (let p = shown.from - 1; p <= shown.to + 1; p++) pages.add(p);
  if (shown.from <= 2) [2, 3].forEach((p) => pages.add(p));
  if (shown.to >= last - 1) [last - 1, last - 2].forEach((p) => pages.add(p));

  const sorted = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
  const items: PageItem[] = [];
  for (const p of sorted) {
    const prev = items[items.length - 1];
    if (typeof prev === 'number' && p - prev === 2) items.push(p - 1);
    else if (typeof prev === 'number' && p - prev > 2) items.push('gap');
    items.push(p);
  }
  return items;
}
