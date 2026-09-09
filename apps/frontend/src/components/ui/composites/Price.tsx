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
  /**
   * Обозначение валюты. На карточках товара в макете «46 210р.» — слитно и с
   * точкой, а в промо-блоке меню шапки «12 030 ₽» — со знаком и пробелом.
   * Два разных написания в одном макете, поэтому вынесено в проп.
   */
  currency?: string;
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
 * Само число и старая цена не переносятся внутри себя, а вот друг под друга
 * встают: в узких местах вроде мини-карточек в меню шапки старая цена иначе
 * вылезала за границу блока.
 *
 * Разделитель разрядов ставит Intl: неразрывный пробел, как в макете. Рубль по
 * умолчанию пишется слитно с числом («46 210р.»), как нарисовано на карточках;
 * в промо-блоке меню шапки другое написание, для него есть проп `currency`.
 */
const format = (value: number, currency: string) =>
  `${new Intl.NumberFormat('ru-RU').format(value)}${currency}`;

export default function Price({
  value,
  oldValue,
  from = false,
  accent,
  currency = 'р.',
  className,
  ...rest
}: PriceProps) {
  const isAccent = accent ?? oldValue !== undefined;
  const tone = isAccent ? 'text-brand-red' : 'text-ink';

  return (
    <span
      {...rest}
      className={cn(
        'inline-flex flex-wrap items-baseline gap-x-2 gap-y-0.5 font-bold',
        tone,
        className,
      )}
    >
      <span className="inline-flex items-baseline gap-1">
        {from && <span className="text-16">от</span>}
        <span className="whitespace-nowrap text-20">{format(value, currency)}</span>
      </span>

      {/* У <s> класс line-through стоит один: если рядом написать no-underline,
          они конфликтуют и зачёркивание гаснет — проверено замером */}
      {oldValue !== undefined && (
        <s className="inline-flex items-baseline gap-1 whitespace-nowrap text-14 line-through">
          {from && <span>от</span>}
          {format(oldValue, currency)}
        </s>
      )}
    </span>
  );
}
