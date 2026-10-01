/** Поля повторяют ответ `/sliders/home`, приводятся к нему в `pages/home/lib/home-sliders.ts` */
export interface PromoItem {
  id: string | number;
  title: string;
  description?: string | null;
  /** Плашка в углу: «СЕЗОННАЯ РАСПРОДАЖА ДО −60%», «ВЫГОДНО» */
  badge?: string | null;
  badgeTone?: 'red' | 'yellow' | 'green';
  image?: string | null;
  /** Ниже 550 — своё кадрирование; если не задано, берётся `image` */
  imageMobile?: string | null;
  to?: string | null;
  linkText?: string | null;
}
