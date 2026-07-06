export const TITLE_SEPARATOR = ' – ';
export const SITE_NAME = 'Светофор Мебели';

export const buildTitle = (page: string): string => {
	return `${page}${TITLE_SEPARATOR}${SITE_NAME}`;
};