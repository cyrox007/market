import type { CatalogSort } from '../lib/catalog-query';
import type { Category, ProductListParams, ProductListResponse } from '../lib/api';

/** SEO для текущей страницы (товар, категория и т.д.) — подставляется в <head> при SSR */
export interface SeoMetaSSR {
  title: string | null;
  description: string | null;
  image: string | null;
  canonical_url: string | null;
  robots: string | null;
  open_graph_title: string | null;
  locale: string | null;
}

export interface SSRContext {
  /** SEO текущей страницы (для подстановки в head при SSR) */
  seo?: SeoMetaSSR | null;
  user?: any;
  /** Сервер уже определил состояние авторизации (в т.ч. что гость не авторизован) — клиенту не нужно повторно дёргать /auth/me. */
  authChecked?: boolean;
  counters?: {
    cartCount: number;
    wishlistCount: number;
    compareCount: number;
  };
  product?: {
    product: any;
    variant_info?: any;
    bundle?: { data: any[] };
    related?: { data: any[] };
  };
  /** Регион покупателя. Сервер его не определяет: /regions/detect удалён на бэкенде (27.10) */
  region?: any;
  /** Дерево категорий для шапки — грузится на всех маршрутах */
  categoryTree?: Category[];
  /** Дерево комнат для шапки — грузится на всех маршрутах */
  roomTree?: Category[];
  // Главная страница
  home?: {
    categories?: any[];
    featuredProducts?: any[];
    newProducts?: any[];
    saleProducts?: any[];
    sliders?: any[];
    interiorIdeas?: any[];
  };
  // Категория каталога
  category?: {
    category?: any;
    products?: ProductListResponse;
    /** Запрос, которым получены products — клиент берёт их, только если его запрос тот же */
    productsParams?: ProductListParams;
    /** API ответил 404 на раздел этого slug — сервер отдал страницу «не найдено» с кодом 404 */
    notFoundSlug?: string;
  };
  // Поиск
  search?: {
    query?: string;
    /** Сортировка, с которой получены products */
    sort?: CatalogSort;
    products?: {
      data: any[];
      meta: any;
    };
  };
  // Наборы
  sets?: {
    data: any[];
    meta: any;
  };
  set?: {
    set: any;
  };
}
