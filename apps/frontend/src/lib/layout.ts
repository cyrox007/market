/** Контейнер контента: одинаков у шапки, блоков главной и подвала. Замеры — market-docs/13-header.md */
export const PAGE_CONTAINER = 'mx-auto w-full max-w-[1440px] px-[100px] max-md:px-6 max-vsm:px-4';

/** Ниже 980 (`max-md:`) — для хука useMediaQuery, где классом не обойтись */
export const BELOW_MD = '(max-width: 979.98px)';
