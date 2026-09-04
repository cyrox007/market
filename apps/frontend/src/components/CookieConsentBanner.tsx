'use client';

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

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
    <div className="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
      <div className="max-w-[1280px] mx-auto px-4 py-3">
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
          <p className="text-xs sm:text-sm text-gray-700 leading-relaxed">
            Мы используем cookie-файлы, чтобы сайт работал корректно и был удобнее. Подробнее — в{' '}
            <Link to="/privacy" className="text-red-600 hover:underline">
              политике конфиденциальности
            </Link>
            .
          </p>
          <button
            type="button"
            onClick={handleAccept}
            className="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 rounded-full bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors"
          >
            Принять
          </button>
        </div>
      </div>
    </div>
  );
}
