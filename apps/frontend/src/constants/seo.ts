export const TITLE_SEPARATOR = ' – ';
/** Без запасного значения в заголовке каждой страницы висело «undefined» */
export const SITE_NAME = import.meta.env.VITE_API_SITENAME || 'Светофор Мебели';

export const buildTitle = (page: string): string => {
  return `${page}${TITLE_SEPARATOR}${SITE_NAME}`;
};
