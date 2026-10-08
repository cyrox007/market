import { useEffect, useLayoutEffect, useRef } from 'react';
import type { ReactNode, RefObject } from 'react';
import { createPortal } from 'react-dom';
import { focusableIn } from './focusable';
import { cn } from '../../../lib/cn';

interface PopoverProps {
  open: boolean;
  /** Клик мимо или Esc. Состояние open меняет вызывающий */
  onClose: () => void;
  /** Кнопка, под которой стоит окно. Нажатие на неё окно не закрывает — она переключает сама */
  anchorRef: RefObject<HTMLElement | null>;
  /** Название окна для читалки */
  label: string;
  /** Нужен, чтобы связать кнопку с окном через aria-controls */
  id?: string;
  /** Низ окна — например, кнопка «Показать N товаров» */
  footer?: ReactNode;
  className?: string;
  children: ReactNode;
}

/** Зазор от кнопки и минимальный отступ от краёв экрана, px */
const GAP = 8;
const EDGE = 16;

/**
 * Окно под кнопкой-триггером: выпадашки «Цена», «Габариты», сортировка.
 * Рендерится в body, чтобы его не обрезал прокручиваемый ряд кнопок. Замеры — market-docs/45.
 */
export default function Popover(props: PopoverProps) {
  if (!props.open) return null;
  return createPortal(<PopoverPanel {...props} />, document.body);
}

function PopoverPanel({
  onClose,
  anchorRef,
  label,
  id,
  footer,
  className,
  children,
}: PopoverProps) {
  const panelRef = useRef<HTMLDivElement>(null);
  // Слушатели вешаются один раз на открытие, а onClose у родителя меняется каждый рендер
  const onCloseRef = useRef(onClose);
  useLayoutEffect(() => {
    onCloseRef.current = onClose;
  });

  useLayoutEffect(() => {
    const place = () => {
      const anchor = anchorRef.current;
      const panel = panelRef.current;
      if (!anchor || !panel) return;
      const a = anchor.getBoundingClientRect();
      const width = panel.getBoundingClientRect().width;
      const rightmost = window.innerWidth - EDGE - width;
      panel.style.top = `${a.bottom + GAP}px`;
      panel.style.left = `${Math.max(EDGE, Math.min(a.left, rightmost))}px`;
    };
    place();
    window.addEventListener('resize', place);
    // capture: прокрутка любого контейнера, в том числе ряда кнопок, сдвигает триггер
    window.addEventListener('scroll', place, true);
    return () => {
      window.removeEventListener('resize', place);
      window.removeEventListener('scroll', place, true);
    };
  }, [anchorRef]);

  useEffect(() => {
    const panel = panelRef.current;
    if (!panel) return;
    (focusableIn(panel)[0] ?? panel).focus({ preventScroll: true });

    const handleKey = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return;
      onCloseRef.current();
      anchorRef.current?.focus();
    };
    const handlePointerDown = (event: PointerEvent) => {
      const target = event.target as Node | null;
      if (!target || panel.contains(target) || anchorRef.current?.contains(target)) return;
      onCloseRef.current();
    };

    document.addEventListener('keydown', handleKey);
    document.addEventListener('pointerdown', handlePointerDown);
    return () => {
      document.removeEventListener('keydown', handleKey);
      document.removeEventListener('pointerdown', handlePointerDown);
    };
  }, [anchorRef]);

  return (
    <div
      ref={panelRef}
      id={id}
      role="dialog"
      aria-label={label}
      tabIndex={-1}
      className={cn(
        'fixed z-50 flex max-h-[calc(100vh-32px)] max-w-[calc(100vw-32px)] flex-col',
        'rounded-popover bg-surface shadow-popover outline-none',
        className,
      )}
    >
      <div className={cn('min-h-0 flex-1 overflow-y-auto p-5', footer ? 'pb-4' : undefined)}>
        {children}
      </div>
      {footer && <div className="shrink-0 px-5 pb-5">{footer}</div>}
    </div>
  );
}
