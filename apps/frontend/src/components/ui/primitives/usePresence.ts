import { useEffect, useState } from 'react';

export const prefersReducedMotion = () =>
  typeof window !== 'undefined' &&
  window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

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
