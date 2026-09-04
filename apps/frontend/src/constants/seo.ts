export const TITLE_SEPARATOR = ' – ';
export const SITE_NAME = import.meta.env.VITE_API_SITENAME;

export const buildTitle = (page: string): string => {
  return `${page}${TITLE_SEPARATOR}${SITE_NAME}`;
};
