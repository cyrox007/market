import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button } from './ui/primitives';
import { PAGE_CONTAINER } from '../lib/layout';

const COOKIE_CONSENT_KEY = 'cookie_consent_v1';

export default function CookieConsentBanner() {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    try {
      const stored = localStorage.getItem(COOKIE_CONSENT_KEY);
      if (stored !== 'accepted') setIsVisible(true);
    } catch {
      // Если localStorage недоступен — просто показываем баннер
      setIsVisible(true);
    }
  }, []);

  const handleAccept = () => {
    try {
      localStorage.setItem(COOKIE_CONSENT_KEY, 'accepted');
    } catch {
      // игнорируем
    }
    setIsVisible(false);
  };

  if (!isVisible) return null;

  return (
    <div className="fixed inset-x-0 bottom-0 z-50 max-vsm:bottom-[calc(68px+env(safe-area-inset-bottom))] border-t border-surface-border bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
      <div className={`${PAGE_CONTAINER} py-4`}>
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
          <p className="text-14 text-ink-secondary leading-relaxed">
            Мы используем cookie-файлы, чтобы сайт работал корректно и был удобнее. Подробнее — в{' '}
            <Link
              to="/privacy"
              className="text-ink underline underline-offset-4 hover:text-brand-green focus-visible:outline-brand-green"
            >
              политике конфиденциальности
            </Link>
            .
          </p>
          <Button onClick={handleAccept} size="sm" className="shrink-0 max-sm:w-full">
            Принять
          </Button>
        </div>
      </div>
    </div>
  );
}
