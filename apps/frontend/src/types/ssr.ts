import type { Category } from '../lib/api';

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
  region?: any;
  /** Дерево категорий для шапки — грузится на всех маршрутах */
  categoryTree?: Category[];
  /** Дерево комнат для шапки — грузится на всех маршрутах */
  roomTree?: Category[];
  /** Список городов для селектора региона в шапке */
  regionsList?: any[];
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
    products?: {
      data: any[];
      meta: any;
    };
  };
  // Поиск
  search?: {
    query?: string;
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
