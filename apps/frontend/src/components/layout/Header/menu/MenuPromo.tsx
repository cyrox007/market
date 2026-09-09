import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { Badge, Button } from '../../../ui/primitives';
import { Price } from '../../../ui/composites';
import { cn } from '../../../../lib/cn';

export interface PromoProduct {
  to: string;
  name: string;
  price: number;
  oldPrice?: number;
}

interface MenuPromoProps {
  badge: string;
  title: string;
  price: number;
  oldPrice?: number;
  to: string;
  buttonLabel?: string;
  suggestionsTitle?: string;
  suggestions?: PromoProduct[];
  className?: string;
}

/** Блок распродажи в меню «Комнаты». Замеры — market-docs/13-header.md */
export default function MenuPromo({
  badge,
  title,
  price,
  oldPrice,
  to,
  buttonLabel = 'Смотреть распродажу',
  suggestionsTitle,
  suggestions = [],
  className,
}: MenuPromoProps) {
  return (
    <div
      className={cn(
        'mt-8 grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-8 rounded-btn bg-brand-yellow/10 p-6',
        'max-md:grid-cols-1',
        className,
      )}
    >
      <div className="flex flex-col items-start gap-3">
        <Badge variant="sale" size="sm" className="uppercase">
          {badge}
        </Badge>
        <h3 className="text-20 font-semibold text-ink">{title}</h3>
        <Price value={price} oldValue={oldPrice} currency=" ₽" />
        <Button
          to={to}
          shape="rounded"
          className="mt-1"
          rightIcon={<ChevronRight className="size-5" />}
        >
          {buttonLabel}
        </Button>
      </div>

      {suggestions.length > 0 && (
        <div>
          {suggestionsTitle && (
            <h4 className="mb-3 text-14 font-medium text-ink">{suggestionsTitle}</h4>
          )}
          <div className="grid grid-cols-3 gap-3 max-vsm:grid-cols-2">
            {suggestions.map((product) => (
              <Link
                key={product.to}
                to={product.to}
                className="flex flex-col justify-between gap-4 rounded-btn bg-surface p-3 outline-none hover:shadow-btn"
              >
                <span className="text-12 text-ink-secondary">{product.name}</span>
                <Price
                  value={product.price}
                  oldValue={product.oldPrice}
                  currency=" ₽"
                  accent={false}
                  className="[&>s]:text-ink-secondary"
                />
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
