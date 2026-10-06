import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import messages from './local/index';

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    lng: 'en',
    fallbackLng: 'en',
    debug: false,
    // Иначе i18next ≥ 25 печатает в консоль рекламу своего сервиса Locize
    showSupportNotice: false,
    resources: messages,
    interpolation: {
      escapeValue: false,
    },
  });

export default i18n;
