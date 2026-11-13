import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';

const sections = ['profile', 'orders', 'favorites', 'addresses', 'bonuses', 'settings'] as const;
export type AccountSection = (typeof sections)[number];

export function useAccountSection() {
  const [params, setParams] = useSearchParams();
  const requested = params.get('section');
  const section = sections.find((value) => value === requested) ?? 'profile';

  useEffect(() => {
    if (requested !== null && (section === 'profile')) {
      setParams((current) => {
        const updated = new URLSearchParams(current);
        updated.delete('section');
        return updated;
      }, { replace: true });
    }
  }, [requested, section, setParams]);

  const selectSection = (next: AccountSection) => {
    setParams((current) => {
      const updated = new URLSearchParams(current);
      if (next === 'profile') updated.delete('section');
      else updated.set('section', next);
      return updated;
    });
  };

  return { section, selectSection };
}
