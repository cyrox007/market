import { buildTitle } from '../../constants/seo';
import type { SeoMeta } from '../../lib/api';

/** Одно и то же на сервере (SSR) и в браузере: поиск не индексируется, ссылки — да */
export const SEARCH_SEO = {
  title: buildTitle('Поиск'),
  description: 'Результаты поиска в интернет-магазине Светофор-Мебель.',
  image: '/logo.png',
  canonical_url: null,
  robots: 'noindex, follow',
  open_graph_title: buildTitle('Поиск'),
  locale: 'ru_RU',
} satisfies SeoMeta;
