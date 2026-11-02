import type { Cart, PaymentMethod } from '../../lib/api';
import type { DeliveryDay } from './DeliverySchedule';
import { dateKey } from './dateKey';

/** Вымышленные данные только для dev-страницы, не ответ API. */
export const previewCart: Cart = {
  items: [1, 2].map((id) => ({
    id,
    product_id: id,
    name: `Кухня «Нагано» — демо ${id}`,
    slug: null,
    image: null,
    sku: null,
    price: 180000,
    total: 180000,
    quantity: 1,
    old_price: 190000,
    old_total: 190000,
    discount_amount: 10000,
  })),
  subtotal: 360000,
  old_subtotal: 380000,
  discount_total: 20000,
  item_count: 2,
  is_empty: false,
};

export const previewPaymentMethods: PaymentMethod[] = [
  {
    id: 1,
    code: 'card_in_store',
    name: 'Райффайзен — демонстрация',
    is_active: true,
    sort_order: 0,
  },
  { id: 2, code: 'card_sberbank', name: 'Сбербанк — демонстрация', is_active: true, sort_order: 1 },
  { id: 3, code: 'cash', name: 'Наличными', is_active: true, sort_order: 2 },
];

export const previewDeliveryDays: DeliveryDay[] = Array.from({ length: 45 }, (_, offset) => {
  const date = new Date(2026, 10, 2 + offset, 12);
  const afternoon = { id: 'afternoon', start: '14:00', end: '17:00' };
  return {
    date: dateKey(date),
    slots:
      date.getDay() === 0
        ? []
        : offset % 3 === 2
          ? [afternoon]
          : [{ id: 'morning', start: '09:00', end: '14:00' }, afternoon],
  };
});
