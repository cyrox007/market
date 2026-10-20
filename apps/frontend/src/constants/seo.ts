export const TITLE_SEPARATOR = ' – ';
// На Node-сервере SSR (tsx) import.meta.env нет — те же переменные из .env там в process.env
const env: Record<string, string | undefined> =
  import.meta.env ??
  (globalThis as { process?: { env: Record<string, string> } }).process?.env ??
  {};

/** Без запасного значения в заголовке каждой страницы висело «undefined» */
export const SITE_NAME = env.VITE_API_SITENAME || 'Светофор Мебели';

export const buildTitle = (page: string): string => {
  return `${page}${TITLE_SEPARATOR}${SITE_NAME}`;
};
