import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { Badge } from '../../../../../components/ui/primitives';
import { cn } from '../../../../../lib/cn';
import type { PromoItem } from '../lib/promo-types';

interface PromoCardProps {
  item: PromoItem;
  className?: string;
}

const BADGE_VARIANT = { red: 'sale', yellow: 'new', green: 'info' } as const;

/** Статичная карточка правой колонки. Замеры — market-docs/17-promo-board.md */
export default function PromoCard({ item, className }: PromoCardProps) {
  const { title, description, badge, badgeTone = 'yellow', image, to, linkText } = item;

  return (
    <article
      className={cn(
        'relative isolate flex flex-col items-start overflow-hidden rounded-card bg-surface-grey p-6',
        // Ниже 1280 карточке не хватает высоты: ужимаем поля
        'max-xl:p-4',
        className,
      )}
    >
      {image ? (
        <img
          src={image}
          alt=""
          loading="lazy"
          className="absolute inset-0 -z-10 size-full object-cover"
        />
      ) : null}

      {/* Та же тень, что на большой карточке: текст здесь тоже белый */}
      {image ? (
        <div className="absolute inset-0 -z-10 bg-gradient-to-r from-black/60 to-transparent" />
      ) : null}

      {badge ? (
        <Badge variant={BADGE_VARIANT[badgeTone]} size="md" className="uppercase tracking-[0.48px]">
          {badge}
        </Badge>
      ) : null}

      {title || description ? (
        <div className="mt-auto flex flex-col gap-1 text-ink-inverse">
          {title ? <h3 className="text-24 font-bold max-xl:text-20">{title}</h3> : null}
          {description ? (
            <p className="max-w-[280px] whitespace-pre-line text-14 max-xl:hidden">{description}</p>
          ) : null}
        </div>
      ) : null}

      {to && linkText ? (
        <Link
          to={to}
          className="mt-4 inline-flex items-center gap-1 text-14 text-ink-inverse outline-none hover:opacity-80 focus-visible:opacity-80"
        >
          {linkText}
          <ChevronRight className="size-4" />
        </Link>
      ) : null}
    </article>
  );
}
