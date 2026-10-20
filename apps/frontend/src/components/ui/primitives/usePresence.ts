import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { prefersReducedMotion } from '../../../lib/motion';

/**
 * Появление и уход с анимацией (market-docs/48): элемент на странице, пока не уедет.
 * `shown` — класс «на месте»: в первом кадре false, чтобы переход сработал.
 * С «уменьшить движение» снимается сразу.
 */
export function usePresence(open: boolean, leaveMs: number) {
  const [rendered, setRendered] = useState(open);
  if (open && !rendered) setRendered(true);

  const [entered, setEntered] = useState(false);
  useEffect(() => {
    if (!rendered) {
      setEntered(false);
      return;
    }
    const frame = requestAnimationFrame(() => setEntered(true));
    return () => cancelAnimationFrame(frame);
  }, [rendered]);

  useEffect(() => {
    if (open || !rendered) return;
    if (prefersReducedMotion()) {
      setRendered(false);
      return;
    }
    // По таймеру, а не transitionend: тот не приходит в скрытой вкладке
    const timer = setTimeout(() => setRendered(false), leaveMs + 50);
    return () => clearTimeout(timer);
  }, [open, rendered, leaveMs]);

  return { rendered, shown: open && entered, leaving: rendered && !open };
}

export interface PresenceMotion {
  enterMs: number;
  leaveMs: number;
}

/** Длительность перехода — из тех же чисел, по которым usePresence снимает окно */
export const transitionMs = (motion: PresenceMotion, leaving: boolean) => ({
  transitionDuration: `${leaving ? motion.leaveMs : motion.enterMs}ms`,
});

/**
 * Закрыть, если окно ещё не уезжает; true — закрыли.
 * Ref: слушатели вешаются один раз, а onClose у родителя новый каждый рендер.
 */
export function useCloseUnlessLeaving(onClose: () => void, leaving: boolean) {
  const latest = useRef({ onClose, leaving });
  useLayoutEffect(() => {
    latest.current = { onClose, leaving };
  });
  return useRef(() => {
    if (latest.current.leaving) return false;
    latest.current.onClose();
    return true;
  });
}
