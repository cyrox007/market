/** Поля повторяют ответ `/sliders`, чтобы блок заработал без переделки, когда бэкендер зальёт данные */
export interface PromoItem {
  id: string | number;
  title: string;
  description?: string | null;
  /** Плашка в углу: «СЕЗОННАЯ РАСПРОДАЖА ДО −60%», «ВЫГОДНО» */
  badge?: string | null;
  badgeTone?: 'red' | 'yellow' | 'green';
  image?: string | null;
  to?: string | null;
  linkText?: string | null;
}
