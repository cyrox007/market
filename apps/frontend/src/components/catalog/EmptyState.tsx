import { Search } from 'lucide-react';
import type { ReactNode } from 'react';

interface EmptyStateProps {
  title: string;
  hint?: string;
  /** Кнопка под текстом — например «Сбросить фильтры» */
  action?: ReactNode;
}

/** Блок «ничего нет» по Figma 730:60495 — каталог и поиск (market-docs/50, 52) */
export default function EmptyState({ title, hint, action }: EmptyStateProps) {
  return (
    <section className="flex flex-col items-center gap-4 rounded-popover bg-surface-grey px-10 py-16 text-center max-vsm:px-4 max-vsm:py-10">
      <span className="flex rounded-pill bg-surface p-[15px] text-ink-secondary">
        <Search size={30} strokeWidth={2} absoluteStrokeWidth aria-hidden />
      </span>
      <div className="flex flex-col items-center gap-3">
        <h2 className="text-24 font-bold text-ink max-vsm:text-20">{title}</h2>
        {hint && <p className="max-w-[554px] text-14 text-ink-secondary">{hint}</p>}
      </div>
      {action}
    </section>
  );
}
