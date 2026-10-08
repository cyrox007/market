import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { cn } from '../../../lib/cn';

interface AccordionSectionProps {
  title: ReactNode;
  /** Начальное состояние, если раскрытием не управляют снаружи */
  defaultOpen?: boolean;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  headingLevel?: 2 | 3 | 4;
  className?: string;
  children: ReactNode;
}

/**
 * Раздел панели фильтров: заголовок со стрелкой, под ним содержимое.
 * Свёрнутое содержимое остаётся в DOM (атрибут hidden) — введённое в поля не теряется.
 * Замеры — market-docs/45.
 */
export default function AccordionSection({
  title,
  defaultOpen = true,
  open,
  onOpenChange,
  headingLevel = 3,
  className,
  children,
}: AccordionSectionProps) {
  const [innerOpen, setInnerOpen] = useState(defaultOpen);
  const isOpen = open ?? innerOpen;
  const bodyId = useId();
  const Heading = `h${headingLevel}` as 'h3';

  const toggle = () => {
    if (open === undefined) setInnerOpen(!isOpen);
    onOpenChange?.(!isOpen);
  };

  const Chevron = isOpen ? ChevronUp : ChevronDown;

  return (
    <div className={cn('flex flex-col', className)}>
      <Heading className="m-0">
        <button
          type="button"
          aria-expanded={isOpen}
          aria-controls={bodyId}
          onClick={toggle}
          className={cn(
            'flex w-full cursor-pointer items-center justify-between gap-2.5 text-left',
            'text-16 font-semibold text-ink',
            'rounded-badge outline-none focus-visible:ring-2 focus-visible:ring-brand-green',
          )}
        >
          <span className="min-w-0">{title}</span>
          <Chevron aria-hidden="true" className="size-6 shrink-0" />
        </button>
      </Heading>
      <div id={bodyId} hidden={!isOpen} className="pt-3">
        {children}
      </div>
    </div>
  );
}
