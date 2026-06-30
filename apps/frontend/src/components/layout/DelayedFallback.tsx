import { useState, useEffect } from 'react';

const DELAY_MS = 120;

/**
 * Показывает fallback только если загрузка длится дольше DELAY_MS.
 * Убирает «мигание» скелета при быстрой загрузке чанка (кэш/быстрая сеть).
 */
export default function DelayedFallback() {
  const [show, setShow] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => setShow(true), DELAY_MS);
    return () => clearTimeout(t);
  }, []);

  if (!show) return null;

  return (
    <div className="flex items-center justify-center min-h-[50vh] bg-white">
      <div className="animate-spin rounded-full h-10 w-10 border-2 border-gray-200 border-t-gray-700" />
    </div>
  );
}
