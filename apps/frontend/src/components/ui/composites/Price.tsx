import type { HTMLAttributes } from 'react';
import { cn } from '../../../lib/cn';

interface PriceOwnProps {
  /** Текущая цена в рублях */
  value: number;
  /** Старая цена: показывается зачёркнутой рядом */
  oldValue?: number;
  /** Приставка «от» — у товаров с вариациями */
  from?: boolean;
  /**
   * Акцентный (красный) цвет. По умолчанию включается сам, когда есть старая
   * цена: в макете акционная цена и зачёркнутая нарисованы одним красным.
   */
  accent?: boolean;
  className?: string;
}

type PriceProps = PriceOwnProps & Omit<HTMLAttributes<HTMLSpanElement>, keyof PriceOwnProps>;

/**
 * Цена с карточки товара.
 *
 * Из макета 07.09.2026 сняты начертание и цвет:
 *   обычная   «от 46 210р.» — тёмная, «от» мельче числа
 *   акционная «от 15 330р.  от 25 330р.» — обе красные, старая зачёркнута
 *
 * Размеры шрифта замерены 07.09.2026:
 *   «от»          16
 *   само число    20
 *   старая цена   14 целиком, вместе со своим «от»
 *
 * Размер 20 в шкале отсутствовал и добавлен в конфиг ради этого места.
 *
 * Разделитель разрядов ставит Intl: неразрывный пробел, как в макете. Рубль
 * пишется слитно с числом («46 210р.»), тоже как в макете, — это не опечатка
 * и не нарушение типографики, а воспроизведение того, что нарисовано.
 */
const format = (value: number) => `${new Intl.NumberFormat('ru-RU').format(value)}р.`;

export default function Price({
  value,
  oldValue,
  from = false,
  accent,
  className,
  ...rest
}: PriceProps) {
  const isAccent = accent ?? oldValue !== undefined;
  const tone = isAccent ? 'text-brand-red' : 'text-ink';

  return (
    <span {...rest} className={cn('inline-flex items-baseline gap-2 font-bold', tone, className)}>
      <span className="inline-flex items-baseline gap-1">
        {from && <span className="text-16">от</span>}
        <span className="text-20">{format(value)}</span>
      </span>

      {/* У <s> класс line-through стоит один: если рядом написать no-underline,
          они конфликтуют и зачёркивание гаснет — проверено замером */}
      {oldValue !== undefined && (
        <s className="inline-flex items-baseline gap-1 text-14 line-through">
          {from && <span>от</span>}
          {format(oldValue)}
        </s>
      )}
    </span>
  );
}
