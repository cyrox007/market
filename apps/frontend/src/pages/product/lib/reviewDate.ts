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

/** Дата отзыва по-русски: бэк отдаёт ISO, раньше — `d F Y` по-английски («28 September 2026») */
export function formatReviewDate(raw: string): string {
  const named = raw.trim().match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/);
  if (named) {
    const month = EN.indexOf(named[2].toLowerCase());
    if (month >= 0) return `${Number(named[1])} ${RU[month]} ${named[3]}`;
  }
  // Дата из самой строки: через Date день зависел бы от пояса сервера и браузера
  const iso = raw.trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (iso && RU[Number(iso[2]) - 1]) return `${Number(iso[3])} ${RU[Number(iso[2]) - 1]} ${iso[1]}`;
  return raw;
}
