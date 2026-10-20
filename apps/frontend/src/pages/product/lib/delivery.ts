import type { ReadyRange, ShippingQuote } from '../../../lib/api';
import { plural } from '../../../lib/plural';

export type { ShippingQuote };

const DAYS = ['день', 'дня', 'дней'] as const;

export function formatReady({ days_min: min, days_max: max, label }: ReadyRange): string {
  if (label) return label;
  if (min === max) {
    if (min === 0) return 'Сегодня';
    if (min === 1) return 'Завтра';
    return `${min} ${plural(min, DAYS)}`;
  }
  return `${min}–${max} ${plural(max, DAYS)}`;
}

export function deliverySummary(quote: ShippingQuote | undefined): {
  delivery: string | null;
  pickup: string | null;
} {
  const delivery = quote?.available
    ? (quote.selected?.delivery ?? quote.options?.[0]?.delivery)
    : undefined;
  const pickup = quote?.pickup?.[0]?.ready;
  return {
    delivery: delivery ? formatReady(delivery) : null,
    pickup: pickup ? formatReady(pickup) : null,
  };
}
