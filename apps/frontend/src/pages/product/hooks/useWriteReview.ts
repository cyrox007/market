import { useEffect, useState } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { withNext } from '../../../lib/safe-next';

const FLAG = 'review';

/** «Написать отзыв»: вошедшему — окно, гостю — вход с возвратом сюда же и `?review=1` */
export function useWriteReview({
  isAuthenticated,
  isLoading,
}: {
  isAuthenticated: boolean;
  isLoading: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [params, setParams] = useSearchParams();
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const flagged = params.has(FLAG);

  useEffect(() => {
    if (!flagged || isLoading) return;
    if (isAuthenticated) setOpen(true);
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        next.delete(FLAG);
        return next;
      },
      { replace: true },
    );
  }, [flagged, isLoading, isAuthenticated, setParams]);

  const start = () => {
    if (isAuthenticated) return setOpen(true);
    const back = new URLSearchParams(params);
    back.set(FLAG, '1');
    navigate(withNext('/login', `${pathname}?${back}`));
  };

  return { open, start, close: () => setOpen(false) };
}
