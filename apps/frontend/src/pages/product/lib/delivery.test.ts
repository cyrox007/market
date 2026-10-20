import { describe, expect, it } from 'vitest';
import { deliverySummary, formatReady, type ShippingQuote } from './delivery';

const range = (days_min: number, days_max: number, label: string | null = null) => ({
  days_min,
  days_max,
  date_min: '2026-10-20',
  date_max: '2026-10-25',
  label,
});

describe('срок готовности', () => {
  it('точно 0 или 1 день — «Сегодня» / «Завтра»', () => {
    expect(formatReady(range(0, 0, 'Сегодня'))).toBe('Сегодня');
    expect(formatReady(range(1, 1, 'Завтра'))).toBe('Завтра');
    // Подписи нет — решаем сами по тем же правилам
    expect(formatReady(range(0, 0))).toBe('Сегодня');
    expect(formatReady(range(1, 1))).toBe('Завтра');
  });

  it('один срок — «N дней» со склонением', () => {
    expect(formatReady(range(2, 2))).toBe('2 дня');
    expect(formatReady(range(5, 5))).toBe('5 дней');
    expect(formatReady(range(21, 21))).toBe('21 день');
  });

  it('разброс — «N–M дней» по последнему числу', () => {
    expect(formatReady(range(3, 5))).toBe('3–5 дней');
    expect(formatReady(range(1, 2))).toBe('1–2 дня');
    expect(formatReady(range(0, 1))).toBe('0–1 день');
  });
});

describe('строки доставки и самовывоза', () => {
  const quote = (over: Partial<ShippingQuote>): ShippingQuote => ({
    available: true,
    selected: null,
    options: [],
    pickup: [],
    ...over,
  });

  it('доставка — по выбранному варианту расчёта, иначе по первому', () => {
    expect(
      deliverySummary(
        quote({
          options: [{ delivery: range(5, 7) }, { delivery: range(2, 3) }],
          selected: { delivery: range(2, 3) },
        }),
      ).delivery,
    ).toBe('2–3 дня');
    expect(deliverySummary(quote({ options: [{ delivery: range(5, 7) }] })).delivery).toBe(
      '5–7 дней',
    );
  });

  it('самовывоз — по ближайшей точке (первой в списке)', () => {
    expect(
      deliverySummary(
        quote({ pickup: [{ ready: range(0, 0, 'Сегодня') }, { ready: range(2, 2) }] }),
      ).pickup,
    ).toBe('Сегодня');
  });

  it('доставки нет (available=false) или вариантов нет — строки нет', () => {
    const none = deliverySummary(quote({ available: false, options: [{ delivery: range(1, 2) }] }));
    expect(none.delivery).toBeNull();
    expect(deliverySummary(quote({})).delivery).toBeNull();
    expect(deliverySummary(quote({})).pickup).toBeNull();
  });

  it('расчёта нет — обе строки пусты', () => {
    expect(deliverySummary(undefined)).toEqual({ delivery: null, pickup: null });
  });
});
