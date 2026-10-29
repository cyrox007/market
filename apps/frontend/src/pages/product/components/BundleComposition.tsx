import { useId } from 'react';
import type { BundlePart } from '../lib/bundle';
import { formatPrice } from '../lib/productText';

/** Из каких реальных товаров соберётся заказ комплекта (макета нет — market-docs/66) */
export default function BundleComposition({ parts }: { parts: BundlePart[] }) {
  const titleId = useId();
  // У простого комплекта цены частей не приходят, а цена товара — уже за весь состав
  const priced = parts.every((p) => p.total !== null);

  return (
    <section
      aria-labelledby={titleId}
      className="flex flex-col gap-3 rounded-[8px] bg-surface-grey p-4 animate-fadeIn motion-reduce:animate-none"
    >
      <h2 id={titleId} className="text-16 font-semibold text-ink">
        Состав комплекта
      </h2>
      <ul className="flex flex-col gap-3">
        {parts.map((part) => (
          <li key={part.key} className="flex items-start justify-between gap-4">
            <div className="flex min-w-0 flex-col gap-0.5">
              <span className="text-14 font-medium text-ink">{part.name}</span>
              <span className="text-12 text-ink-secondary">
                Арт. {part.sku} · {part.quantity} шт.
              </span>
            </div>
            {part.total !== null && (
              <span className="whitespace-nowrap text-14 font-semibold text-ink">
                {formatPrice(part.total)}
              </span>
            )}
          </li>
        ))}
      </ul>
      {!priced && (
        <p className="text-12 text-ink-secondary">
          Цена указана за весь комплект.
        </p>
      )}
    </section>
  );
}
