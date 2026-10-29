import { describe, expect, it } from 'vitest';
import { formatReviewDate } from './reviewDate';

describe('дата отзыва', () => {
  it('английский месяц от бэка — по-русски в родительном падеже', () => {
    expect(formatReviewDate('28 September 2026')).toBe('28 сентября 2026');
    expect(formatReviewDate('02 May 2026')).toBe('2 мая 2026');
  });

  it('ISO-дата — тоже', () => {
    expect(formatReviewDate('2026-08-21T10:00:00Z')).toBe('21 августа 2026');
  });

  it('непонятное — как есть', () => {
    expect(formatReviewDate('вчера')).toBe('вчера');
  });

  it('ISO с поясом — день по дате бэка, а не по часовому поясу сервера или браузера', () => {
    // Около полуночи new Date дал бы соседний день в UTC и расхождение SSR с гидратацией
    expect(formatReviewDate('2026-10-28T23:30:00+03:00')).toBe('28 октября 2026');
    expect(formatReviewDate('2026-10-01T00:10:00+03:00')).toBe('1 октября 2026');
  });
});
