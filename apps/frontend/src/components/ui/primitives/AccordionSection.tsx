import { useEffect, useId, useState } from 'react';
import type { ReactNode, TransitionEvent } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '../../../lib/cn';
import { prefersReducedMotion } from './usePresence';

const EXPAND_MS = 300;

interface AccordionSectionProps {
  title: ReactNode;
  /** Начальное состояние, если раскрытием не управляют снаружи */
  defaultOpen?: boolean;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  headingLevel?: 2 | 3 | 4;
  className?: string;
  /** Ширина заголовка, если она уже раздела: в «Все фильтры» — 280 (Figma 730:53152) */
  headerClassName?: string;
  children: ReactNode;
}

/**
 * Раздел панели фильтров: заголовок со стрелкой, под ним содержимое.
 * Свёрнутое содержимое остаётся в DOM — введённое в поля не теряется. Раскрытие плавное:
 * высота через grid-template-rows, содержимое проявляется, стрелка поворачивается (market-docs/48).
 * Свёрнутое — visibility: hidden (уходит в конце перехода) и inert: Tab в него не заходит.
 * Замеры — market-docs/45.
 */
export default function AccordionSection({
  title,
  defaultOpen = true,
  open,
  onOpenChange,
  headingLevel = 3,
  className,
  headerClassName,
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

  // Пока раскрывается — обрезаем; раскрылось — снимаем, иначе режутся кольца фокуса у полей
  const [settled, setSettled] = useState(isOpen);
  const [prevOpen, setPrevOpen] = useState(isOpen);
  if (prevOpen !== isOpen) {
    setPrevOpen(isOpen);
    setSettled(false);
  }
  const handleTransitionEnd = (event: TransitionEvent<HTMLDivElement>) => {
    if (event.target === event.currentTarget && event.propertyName === 'grid-template-rows') {
      setSettled(isOpen);
    }
  };
  // Без перехода transitionend не придёт: «уменьшить движение» — сразу, иначе запасной таймер
  useEffect(() => {
    if (!isOpen || settled) return;
    if (prefersReducedMotion()) {
      setSettled(true);
      return;
    }
    const timer = setTimeout(() => setSettled(true), EXPAND_MS + 50);
    return () => clearTimeout(timer);
  }, [isOpen, settled]);

  return (
    <div className={cn('flex flex-col', className)}>
      <Heading className={cn('m-0', headerClassName)}>
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
          <ChevronDown
            aria-hidden="true"
            className={cn(
              'size-6 shrink-0 transition-transform duration-300 ease-out motion-reduce:transition-none',
              isOpen && 'rotate-180',
            )}
          />
        </button>
      </Heading>
      <div
        id={bodyId}
        inert={!isOpen}
        onTransitionEnd={handleTransitionEnd}
        className={cn(
          // grid-cols-1 — minmax(0, 1fr): иначе колонка растягивается по ширине полей
          'grid grid-cols-1 transition-[grid-template-rows,opacity,visibility] duration-300 ease-out',
          'motion-reduce:transition-none',
          isOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0',
        )}
        style={{ visibility: isOpen ? 'visible' : 'hidden' }}
      >
        <div className={cn('min-h-0', !(isOpen && settled) && 'overflow-hidden')}>
          <div className="pt-3">{children}</div>
        </div>
      </div>
    </div>
  );
}
