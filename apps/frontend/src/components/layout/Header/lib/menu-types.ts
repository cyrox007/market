import type { ReactNode } from 'react';

export interface MenuLink {
  to: string;
  label: string;
  /** Маршрута под раздел ещё нет: рисуем приглушённым и не кликаем */
  disabled?: boolean;
}

export interface MenuGroup {
  /** Заголовок колонки. У каталога его нет, у «Комнат» есть */
  title?: string;
  links: MenuLink[];
}

export interface MenuSection {
  id: string;
  label: string;
  /** Пункт без раскрытия — просто ссылка. Такие «Сток» и «Акции» в каталоге */
  to?: string;
  /** Выделенный пункт: в макете «Сток» и «Акции» набраны жирным и крупнее */
  emphasized?: boolean;
  /** Заголовок правой панели, если отличается от подписи пункта */
  heading?: string;
  groups?: MenuGroup[];
  /** Раздел без источника данных: приглушён и не раскрывается */
  disabled?: boolean;
  /** Блок распродажи под колонками — есть только у «Комнат» */
  promo?: ReactNode;
}
