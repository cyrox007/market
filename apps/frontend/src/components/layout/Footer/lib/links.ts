export interface FooterLink {
  label: string;
  to?: string;
}

export interface FooterColumn {
  title: string;
  links: FooterLink[];
}

/** Ссылка без `to` рисуется приглушённой и не кликается: маршрута под раздел ещё нет */
export const FOOTER_COLUMNS: FooterColumn[] = [
  {
    title: 'Компания',
    links: [
      { label: 'О нас', to: '/about' },
      { label: 'Магазины', to: '/stores' },
      { label: 'Документы', to: '/documents' },
    ],
  },
  {
    title: 'Клиентам',
    links: [
      { label: 'Кредит и рассрочка' },
      { label: 'Доставка и сборка', to: '/delivery' },
      { label: 'Контакты', to: '/contacts' },
      { label: 'Возврат товара', to: '/returns' },
    ],
  },
  {
    title: 'Дополнительно',
    links: [
      { label: 'Производители' },
      { label: 'Подарочные сертификаты' },
      { label: 'Партнерская программа' },
      { label: 'Акции', to: '/collections/sale' },
    ],
  },
  {
    title: 'Конфиденциальность',
    links: [
      { label: 'Политика конфиденциальности', to: '/privacy' },
      { label: 'Обработка cookie-файлов', to: '/cookies' },
      { label: 'Договор оферты', to: '/oferta' },
    ],
  },
];

export const FOOTER_CONTACTS = {
  phone: '8 (800) 222-85-86',
  email: 'info@svetofor-mebel.ru',
  hours: ['c 09.00 до 21.00', '(Без перерыва и выходных)'],
};
