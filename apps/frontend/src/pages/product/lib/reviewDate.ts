const RU = [
  'января',
  'февраля',
  'марта',
  'апреля',
  'мая',
  'июня',
  'июля',
  'августа',
  'сентября',
  'октября',
  'ноября',
  'декабря',
];
const EN = [
  'january',
  'february',
  'march',
  'april',
  'may',
  'june',
  'july',
  'august',
  'september',
  'october',
  'november',
  'december',
];

/** Бэк отдаёт `d F Y` по-английски («28 September 2026», market-docs/15) — показываем «28 сентября 2026» */
export function formatReviewDate(raw: string): string {
  const named = raw.trim().match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/);
  if (named) {
    const month = EN.indexOf(named[2].toLowerCase());
    if (month >= 0) return `${Number(named[1])} ${RU[month]} ${named[3]}`;
  }
  if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
    const date = new Date(raw);
    if (!Number.isNaN(date.getTime()))
      return `${date.getDate()} ${RU[date.getMonth()]} ${date.getFullYear()}`;
  }
  return raw;
}
